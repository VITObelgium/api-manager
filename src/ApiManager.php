<?php

namespace Drupal\api_manager;

use Drupal\Component\Serialization\Json;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

define('API_MANAGER_LOG_ID', 'api_manager');

/**
 * The class that serves all the logic and handles the synchronisation.
 */
class ApiManager {

  public function __construct() {
    set_time_limit(0);
  }

  /**
   * The data coming from the api.
   *
   * @var array
   */
  protected $data = [];

  /**
   * The entity type the API is importing.
   *
   * @var string
   */
  protected $entityType = '';

  /**
   * The active current item the API is importing.
   *
   * @var string
   */
  protected $activeCurrentItem = '';

  /**
   * Local content (nodes) in our database.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  protected $localContent = [];

  /**
   * The main import function, ran by drush.
   *
   * @param triggeredEntity
   *   If trigger is externale, an object of type entity can be included
   * @return function ApiManagerLogError
   *   Nothing returned.
   */
  public function startJobs($triggeredEntity = NULL) {
    // Get all available API calls.
    $storage = \Drupal::entityTypeManager()->getStorage('api_manager');
    $ids = \Drupal::entityQuery('api_manager')->execute();
    $apiList = $storage->loadMultiple($ids);
    $apiOrder = [];
    $apis = [];

    // First and important check: make sure tha api interval is not set to "external trigger". If so: unset it here
    foreach($apiList as $checkForExternalTriggerOnly) {
      if(!is_numeric($checkForExternalTriggerOnly->get('api_minutes'))) {
        if(!$triggeredEntity) {
          unset($apiList[$checkForExternalTriggerOnly->id()]);
        }
      }
    }

    // If entity is called from external trigger, pass it in here
    if($triggeredEntity) {
      $apiList = [$triggeredEntity];
    }

    foreach ($apiList as $api) {
      if(!isset($apiOrder[$api->get('api_manager_weight')])) {
        $apiOrder[$api->get('api_manager_weight')] = [$api->id()];
      } else {
        $apiOrder[$api->get('api_manager_weight')][] = $api->id();
      }

      $data = [
        'id' => $api->id(),
        'name' => $api->label(),
        'api_url' => $api->get('api_url'),
        'user_id' => $api->get('api_user_id'),
        'destination_entity' => $api->get('api_destination_entity'),
        'api_sync_field' => $api->get('api_sync_field'),
        'api_unique_id' => $api->get('api_unique_id'),
        'api_updated_identifier' => $api->get('api_updated_identifier'),
        'api_type' => $api->get('api_type'),
        'api_minutes' => $api->get('api_minutes'),
        'api_manager_textfield' => $api->get('api_manager_textfield'),
        'api_manager_textarea' => $api->get('api_manager_textarea'),
        'api_manager_image' => $api->get('api_manager_image'),
        'api_manager_image_root' => $api->get('api_manager_image_root'),
        'api_manager_entity' => $api->get('api_manager_entity'),
        'api_manager_list' => $api->get('api_manager_list'),
        'api_manager_integer' => $api->get('api_manager_integer'),
        'api_manager_date' => $api->get('api_manager_date'),
        'api_manager_geolocation' => $api->get('api_manager_geolocation'),
        'api_active' => $api->get('api_manager_active'),
        'api_destination_language' => $api->get('api_destination_language'),
      ];
      $apis[$api->id()] = $data;
    }
    ksort($apiOrder);
    // Api's are now in right order
    $finalApiOrder = [];
    foreach($apiOrder as $orderedApi) {
      foreach($orderedApi as $ApiItem) {
        $finalApiOrder[] = $ApiItem;
      }
    }

    foreach ($finalApiOrder as $api) {
      // First, check if the api is set to interval, or to external trigger
      if ($apis[$api]['api_active'] == 1) {
        $this->entityType = ApiManagerDetermineEntityType($apis[$api]['destination_entity']);
        if (!$this->entityType) {
          return ApiManagerLogError(t('Entity type: %type does not exist (anymore), aborting.', ['%type' => $apis[$api]['destination_entity']]));
        }

        $last_run = \Drupal::state()->get('api_manager_last_check_' . $apis[$api]['id'], 0);
        \Drupal::state()->set('api_manager_last_check_' . $apis[$api]['id'], \Drupal::time()->getRequestTime());

        if(!$triggeredEntity) {
          // If cron: check latest timestamp and frequency
          $frequency = $apis[$api]['api_minutes'] * 60;
          $now = \Drupal::time()->getRequestTime();
          if ($last_run < ($now - $frequency)) {
            self::sync($apis[$api]);
          }
        } else {
          // If externally triggered: fire
          self::sync($apis[$api]);
        }

      } else {
        return ApiManagerLogError(t('API called for %api, but is is not active', ['%api' => $apis[$api]['id']]));
      }
    }

    return 1;
  }

  /**
   * The sync function.
   *
   * @param array $data
   *   The full list of API details.
   *
   * @return null
   *   Nothing
   */
  public function sync($data) {

    // Reset import values to 0.
    \Drupal::state()->set('api_manager_current_batch_' . $data['id'] . '_new', 0);
    \Drupal::state()->set('api_manager_current_batch_' . $data['id'] . '_updated', 0);
    \Drupal::state()->set('api_manager_current_batch_' . $data['id'] . '_skipped', 0);
    \Drupal::state()->set('api_manager_current_batch_' . $data['id'] . '_deleted', 0);
    \Drupal::state()->set('api_manager_current_batch_' . $data['id'] . '_error', 0);

    if (!$this->request($data['api_url'])) {
      return ApiManagerLogError(t('Could not get data for url: %url.', ['%url' => $data['api_url']]));
    }

    try {

      $total = count($this->data);
      \Drupal::state()->set('api_manager_current_batch_' . $data['id'] . '_count', $total);

      $entityType = ApiManagerDetermineEntityType($data['destination_entity']);
      $localContent = ApiManagerLoadLocalContent($data['api_destination_language'], $data['api_sync_field'], $entityType, $data['destination_entity']);

      // We delete differences between local data and external data
      $itemsToDelete = $localContent;
      foreach($this->data as $syncItem => $externalItem) {
        $syncValue = $data['destination_entity'] . '_sync_' . $externalItem[$data['api_unique_id']];
        unset($itemsToDelete[$syncValue]);
      }
      // itemsToDelete now holds all remaining nodes and tags e.g. can be removed
      // can be deleted
      foreach($itemsToDelete as $syncKeys => $entity) {
        $entity->delete();
        $deleted = \Drupal::state()->get('api_manager_current_batch_' . $data['id'] . '_deleted', 0) + 1;
        \Drupal::state()->set('api_manager_current_batch_' . $data['id'] . '_deleted', $deleted);
        unset($localContent[$syncKeys]);
      }

      foreach ($this->data as $item) {

          self::syncItem([
            'item' => $item,
            'data' => $data,
            'functionContext' => [
              'activeCurrentItem' => $this->activeCurrentItem,
              'localContent' => $localContent,
              'entityType' => $this->entityType,
            ],
          ]);
      }

    }
    catch (\Exception $e) {
      return ApiManagerLogException($e);
    }
  }

  /**
   * Create update item.
   *
   * @param array $importObject
   *
   * @return bool
   *   Success.
   */
  public static function syncItem($importObject, &$context  = NULL) {

    $item = $importObject['item'];
    $data = $importObject['data'];

    \Drupal::state()->set('api_manager_current_batch', $data['id']);

    $isNew = FALSE;

    if (!(isset($item[$data['api_unique_id']]) && ($id = $item[$data['api_unique_id']]))) {
      $importObject['functionContext']['status']['error']++;
      return ApiManagerLogError(t('Could not retrieve item id for item.'));
    }

    $importObject['functionContext']['activeCurrentItem'] = $item[$data['api_unique_id']];

    $syncId = $data['destination_entity'] . '_sync_' . $item[$data['api_unique_id']];

    // Check if already exists.
    if (isset($importObject['functionContext']['localContent'][$syncId])) {
      // Exists, load item.
      $entity = $importObject['functionContext']['localContent'][$syncId];
      unset($importObject['functionContext']['localContent'][$syncId]);
      // If no updated date given in webservice, always update.
      $needsUpdate = TRUE;

      if ($importObject['functionContext']['entityType'] === 'node') {
        if (isset($item[$data['api_updated_identifier']])) {
          // If updated date given, do not update and do check below.
          $needsUpdate = FALSE;
          $entityCreatedDate = $entity->get('changed')->value;
          $itemUpdatedDate = strtotime($item[$data['api_updated_identifier']]);
          if ($entityCreatedDate < $itemUpdatedDate) {
            $needsUpdate = TRUE;
            $updated = \Drupal::state()->get('api_manager_current_batch_' . $data['id'] . '_updated', 0) + 1;
            \Drupal::state()->set('api_manager_current_batch_' . $data['id'] . '_updated', $updated);
            $context['results'][] = $entity->label();
            $context['message'] = t('Updating @title', array('@title' => $entity->label()));
          }
          else {
            $skipped = \Drupal::state()->get('api_manager_current_batch_' . $data['id'] . '_skipped', 0) + 1;
            \Drupal::state()->set('api_manager_current_batch_' . $data['id'] . '_skipped', $skipped);
            $context['results'][] = $entity->label();
            $context['message'] = t('Skipping @title', array('@title' => $entity->label()));
          }
        }
        else {
          $needsUpdate = TRUE;
          $updated = \Drupal::state()->get('api_manager_current_batch_' . $data['id'] . '_updated', 0) + 1;
          \Drupal::state()->set('api_manager_current_batch_' . $data['id'] . '_updated', $updated);
          $context['results'][] = $entity->label();
          $context['message'] = t('Updating @title', array('@title' => $entity->label()));
        }
      }
      if ($importObject['functionContext']['entityType'] === 'taxonomy') {
        $needsUpdate = TRUE;
        $updated = \Drupal::state()->get('api_manager_current_batch_' . $data['id'] . '_updated', 0) + 1;
        \Drupal::state()->set('api_manager_current_batch_' . $data['id'] . '_updated', $updated);
        $context['results'][] = $entity->label();
        $context['message'] = t('Updating @title', array('@title' => $entity->label()));
      }
    }
    else {
      // Does not exist yet, create one.
      try {
        $needsUpdate = TRUE;
        $isNew = TRUE;
        switch ($importObject['functionContext']['entityType']) {
          case 'node':
            $entity = Node::create(
              [
              'type' => $data['destination_entity'],
              'langcode' => $data['api_destination_language'],
              $data['api_sync_field'] => $syncId
              ]
            );
            break;

          case 'taxonomy':
            $entity = Term::create([
              'vid' => $data['destination_entity'],
              'langcode' => $data['api_destination_language'],
              $data['api_sync_field'] => $syncId,
              ]
            );
            break;
        }
        $new = \Drupal::state()->get('api_manager_current_batch_' . $data['id'] . '_new', 0) + 1;
        \Drupal::state()->set('api_manager_current_batch_' . $data['id'] . '_new', $new);
      }
      catch (\Exception $e) {
        $new = \Drupal::state()->get('api_manager_current_batch_' . $data['id'] . '_new', 0) - 1;
        \Drupal::state()->set('api_manager_current_batch_' . $data['id'] . '_new', $new);
        return ApiManagerLogError(
          t('Could not create new node type: %type',
            ['%type' => $data['destination_entity']]
          ), $e
        );
      }
    }

    try {

      if($needsUpdate) {

        $entity->set($data['api_sync_field'], $syncId);

        // Map the regular textfields to the node.
        $textfield = ApiManagerParseApiFields($data['api_manager_textfield'], 'Regular textfield mapping');
        foreach ($textfield as $key => $entityField) {
          $value = ApiManagerParseApiStructure($key, $entityField, $item);
          if (isset($value) && !empty($value)) {
            $entity->set($entityField, $value);
            if ($entityField == 'title') {
              $context['results'][] = $value;
              if ($isNew) {
                $context['message'] = t('Creating @title', array('@title' => $value));
              }
              else {
                $context['message'] = t('Updating @title', array('@title' => $value));
              }
            }
          }
        }

        // Map the textarea textfields to the node.
        if ($data['api_manager_textarea']) {
          $textarea = ApiManagerParseApiFields($data['api_manager_textarea'], 'Textarea mapping');
          if ($textarea) {
            foreach ($textarea as $key => $entityField) {
              $value = ApiManagerParseApiStructure($key, $entityField, $item);
              if (isset($value) && !empty($value)) {
                $entity->set($entityField, ['value' => $value, 'format' => 'filtered_html']);
              }
            }
          }
        }

        // Map the list fields to the node.
        if ($data['api_manager_list']) {
          $list = ApiManagerParseApiFields($data['api_manager_list'], 'List mapping');
          if ($list) {
            if (count($list) > 0) {
              $entityManager = \Drupal::service('entity_field.manager');
            }
            foreach ($list as $key => $entityField) {
              $fields = $entityManager->getFieldStorageDefinitions('node', $data['destination_entity']);
              $options = options_allowed_values($fields[trim(preg_replace('/\s\s+/', ' ', $entityField))]);
              $value = ApiManagerParseApiStructure($key, $entityField, $item);
              if (isset($value) && !empty($value) && array_key_exists($value, $options)) {
                $entity->set($entityField, $value);
              }
              if (!empty($value && !array_key_exists($value, $options))) {
                $importObject['functionContext']['status']['new']--;
                $importObject['functionContext']['status']['error']++;
                return ApiManagerLogError(
                  t(
                    'Option %option asked, but not available on field %field of type %type (api item %identifier).', [
                      '%option' => $value,
                      '%field' => $entityField,
                      '%type' => $data['destination_entity'],
                      '%identifier' => $item[$data['api_unique_id']],
                    ]
                  )
                );
              }
            }
          }
        }

        // Map the entity references to its node.
        if ($data['api_manager_entity']) {
          $reference = ApiManagerParseApiFields($data['api_manager_entity'], 'Entity reference mapping');
          foreach ($reference as $key => $entityField) {
            $hasEntitytype = preg_match('/\[.*?\]/', $key, $matches);
            if ($hasEntitytype) {
              $entityBundleName = str_replace(array('[',']'), '', $matches[0]);
              $entityBundle = ApiManagerDetermineEntityType($entityBundleName);
              if ($entityBundle == 'taxonomy') {
                $entityBundle = 'taxonomy_term';
              }
              $key = str_replace($matches[0], '', $key);
              $value = ApiManagerParseApiStructure($key, $entityField, $item);
              if (is_array($value)) {
                foreach ($value as $index => $referencedItem) {
                  $entityIds = \Drupal::entityQuery($entityBundle)
                    ->condition($data['api_sync_field'], $entityBundleName . '_sync_' . $referencedItem, '=')
                    ->execute();
                  foreach ($entityIds as $entityId) {
                    if ($index == 0) {
                      $entity->set($entityField, ['target_id' => $entityId]);
                    }
                    else {
                      $entity->get($entityField)->appendItem([
                        'target_id' => $entityId,
                        ]
                      );
                    }
                  }
                }
              }
              else {
                $entityIds = \Drupal::entityQuery($entityBundle)
                  ->condition($data['api_sync_field'], $entityBundleName . '_sync_' . $value, '=')
                  ->execute();
                foreach ($entityIds as $entityId) {
                  $entity->set($entityField, ['target_id' => $entityId]);
                }
              }
            }
            else {
              return ApiManagerLogError(
                t('Wrong markup detected in datastructure of field: %field. Dit you specify a target entity type in square brackets correctly?', [
                  '%field' => 'Entity reference mapping',
                  ]
                )
              );
            }
          }
        }

        // Map the images to the node
        if($data['api_manager_image']) {
          $image = ApiManagerParseApiFields($data['api_manager_image'], 'Images mapping');
          if ($image) {
            foreach ($image as $key => $entityField) {
              $value = ApiManagerParseApiStructure($key, $entityField, $item);
              if (isset($value) && !empty($value)) {
                $fid = ApiManagerFetchExternalFile($value, $data['api_manager_image_root']);
                if($fid) {
                  $entity->set($entityField, $fid);
                }
              }
            }
          }
        }

        // Map the dates types
        if($data['api_manager_date']) {
          $date = ApiManagerParseApiFields($data['api_manager_date'], 'Date mapping');
          if ($date) {
            foreach ($date as $key => $entityField) {
              $value = ApiManagerParseApiStructure($key, $entityField, $item);
              if (isset($value) && !empty($value)) {
                if ($entityField === 'created') {
                  if(!empty($item[$data['api_updated_identifier']])) {
                    $value = ApiManagerParseApiStructure($data['api_updated_identifier'], $entityField, $item);
                    $value = strtotime($value);
                    $entityField = 'changed';
                  } else {
                    $value = strtotime($value);
                  }
                }
                $entity->set($entityField, $value);
              }
            }
          }
        }

        // Map integers to the node
        if($data['api_manager_integer']) {
          $integer = ApiManagerParseApiFields($data['api_manager_integer'], 'Integer mapping');
          if($integer) {
            foreach($integer as $key => $entityField) {
              $value = ApiManagerParseApiStructure($key, $entityField, $item);
              if(isset($value) && !empty($value)) {
                $entity->set($entityField, $value);
              }
            }
          }
        }

        // Map geolocations to the node
        if($data['api_manager_geolocation']) {
          $geo = ApiManagerParseApiFields($data['api_manager_geolocation'], 'Geolocation mapping');
          if($geo) {
            foreach($geo as $key => $entityField) {
              $latlong = explode('+',$key);
              $lat = ApiManagerParseApiStructure($latlong[0], $entityField, $item);
              $long = ApiManagerParseApiStructure($latlong[1], $entityField, $item);
              if(isset($lat) && !empty($lat) && !empty($long) && isset($long)) {
                $entity->set($entityField, ["lat" => $lat,"lng" => $long]);
              }
            }
          }
        }

        switch($importObject['functionContext']['entityType']) {
        case 'node':
          $entity->setPublished(true);
          $entity->setOwnerId($data['user_id']);
          break;
        case 'taxonomy':
          if(!$item['parent_id']) {
          // no parent, so this is a parent
          }
          else {
            $values = \Drupal::entityQuery('taxonomy_term')->condition($data['api_sync_field'], $data['destination_entity'] . '_sync_' . $item['parent_id'])->execute();
            $tag_exists = !empty($values);
            if($tag_exists) {
              $entity->parent = ['target_id' => reset($values)];
            }
          }
          break;
        }

        $entity->save();
      }

    }
    catch (\Exception $e) {
      $new = \Drupal::state()->get('api_manager_current_batch_'.$data['id'].'_new', 0) - 1;
      \Drupal::state()->set('api_manager_current_batch_'.$data['id'].'_new', $new);
      $error = \Drupal::state()->get('api_manager_current_batch_'.$data['id'].'_error', 0) + 1;
      \Drupal::state()->set('api_manager_current_batch_'.$data['id'].'_error', $error);

      return ApiManagerLogError(t('Could not set values to %type with id: %id. Make sure all mapped field are correct. '.$e, [
            '%id' => $syncId,
            '%type' => $importObject['functionContext']['entityType']
            ]
        ), $e
      );
    }

    return true;
  }

  /**
   * Create request.
   *
   * @param string $url
   *   Endpoint directory.
   *
   * @return bool
   *   Success.
   */
  protected function request($url) {
    try {
      $request = \Drupal::httpClient()->get($url);
    }
    catch (\Exception $e) {
      return ApiManagerLogError(t(
            'Could not get request url: %url.', [
            '%url' => $url,
            ]
        ), $e
      );
    }

    if (!$data = $request->getBody()) {
      return ApiManagerLogError(t('Could not get response body for url: %url.', [
            '%url' => $url,
            ]
        )
      );
    }

    try {
      if (!$this->data = Json::decode((string) $data)) {
        return ApiManagerLogError(t('Could not get response json for url: %url.', [
              '%url' => $url,
              ]
          )
        );
      }
    }
    catch (\Exception $e) {
      return ApiManagerLogError(t(
            'Could not parse request body JSON for url: %url. See next exception.', [
            '%url' => $url,
            ]
        ), $e
      );
    }

    return true;
  }

}

