<?php
namespace Drupal\api_manager\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the ApiManager add and edit forms.
 */
class ApiForm extends EntityForm {

  /**
   * Constructs an ApiManagerForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $api_manager = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->label(),
      '#description' => $this->t("Label for the Api."),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $api_manager->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
      ],
      '#disabled' => !$api_manager->isNew(),
    ];

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#maxlength' => 255,
      '#options' => [
        'json' => 'Json call'
      ],
      '#access' => FALSE,
      '#default_value' => ($api_manager->get('type')) ? $api_manager->get('type') : 0,
      '#required' => TRUE,
      '#description' => $this->t('Choose a call type. Currently only JSON support available.')
    ];

    $form['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Api url'),
      '#maxlength' => 510,
      '#default_value' => $api_manager->get('api_url'),
      '#required' => TRUE,
      '#description' => $this->t('The url to request the data from.')
    ];

    $form['api_user_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User id'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_user_id'),
      '#required' => TRUE,
      '#description' => $this->t('The user id that will become the author of imported nodes.')
    ];

    // Map content type field for the api.
    $typesAvailable = \Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->loadMultiple();
    $Types = [];

    foreach ($typesAvailable as $AvailableType) {
      $Types[$AvailableType->id()] = $AvailableType->label() . ' (node)';
    }
    $vocabularies = taxonomy_vocabulary_get_names();
    foreach($vocabularies as $vid) {
      $voc =  \Drupal\taxonomy\Entity\Vocabulary::load($vid);
      $Types[$vid] = $voc->label()  . ' (taxonomy)';
    }

    $form['api_destination_entity'] = [
      '#type' => 'select',
      '#title' => $this->t('The content type or taxonomy to the API will create items for.'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_destination_entity'),
      '#options' => $Types,
      '#required' => TRUE,
      '#description' => $this->t('Choose a content type or vocabulary.')
    ];

    $form['api_sync_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content type: unique map field'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_sync_field'),
      '#required' => TRUE,
      '#description' => $this->t('The field on the content type where the api can store its unique id. f.e. \'field_sync_id\'. The field should be a regular text field.')
    ];

    $form['api_unique_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Api object: unique id identifier'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_unique_id'),
      '#required' => TRUE,
      '#description' => $this->t('The id key or unique identifier of your import items. f.e. \'id\', \'item_id\', \'item_uuid\'. The key should be available on each item of your import items.')
    ];

    $form['api_updated_identifier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Api object: updated date identifier'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_updated_identifier'),
      '#description' => $this->t('The id key or unique identifier to see if an item is updated. f.e. \'updated\', \'updated_date\'. If identified, the sync will only update a node if an item was updated. If empty, the sync will update everything each time.')
    ];

    $form['api_taxonomy_parent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Api object: parent item identifier (optional, for taxonomy only)'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_taxonomy_parent'),
      '#description' => $this->t("If the term items have hierarchy, specify here the identifier of the parent item f.e. 'parent_id'.")
    ];

    $allowed = ['und' => 'undefined'];
    $langcodes = \Drupal::languageManager()->getLanguages();
    $langcodesList = array_keys($langcodes);
    foreach($langcodesList as $langcode) {
      $allowed[$langcode] = $langcode;
    }

    $form['api_destination_language'] = [
      '#type' => 'select',
      '#title' => $this->t('The destination language of the nodes or terms'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_destination_language'),
      '#required' => TRUE,
      '#options' => $allowed,
      '#description' => $this->t('Nodes or terms that will be created will have this language.')
    ];

    $form['api_manager_textfield'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Regular textfield mapping'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_manager_textfield'),
      '#required' => TRUE,
      '#description' => $this->t('Map your fields like this: api_item_key|content_type_field, f.e. name|title, organisation_name|field_organisation_name. use \'title\' for nodes and \'name\' for terms.')
    ];

    $form['api_manager_textarea'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Textarea mapping'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_manager_textarea'),
      '#description' => $this->t('Map your fields like this: api_item_key|content_type_field, f.e. organisation_description|body, oranisation_description|field_organisation_description.')
    ];

    $form['api_manager_image'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Image mapping'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_manager_image'),
      '#description' => $this->t('Map your images like this: api_item_key|content_type_field, f.e. organisation_logo|field_organisation_logo.')
    ];

    $form['api_manager_image_root'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image mapping root url'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_manager_image_root'),
      '#description' => $this->t('If the api uses relative urls like "/uploads/image.jpg, you can specify the root here f.e. "https://mysite.com".')
    ];

    $form['api_manager_entity'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Entity reference mapping'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_manager_entity'),
      '#description' => $this->t('Map your references. These should point to IDs of other api items. Make sure you respect the order of import. Setup: api_item_key[entity_machine_name]|content_type_field, f.e. organisation_id[organisation]|field_organisation_reference. Both machine names for taxonomy or node are allowed.')
    ];

    $form['api_manager_list'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List item mapping'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_manager_list'),
      '#description' => $this->t('Map your list items like this: api_item_key|content_type_field, f.e. organisation_type|field_organisation_type.')
    ];

    $form['api_manager_integer'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Integer mapping'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_manager_integer'),
      '#description' => $this->t('Map your numbers like this: api_item_key|content_type_field, f.e. organisation_id|field_organisation_id.')
    ];

    $form['api_manager_date'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Dates mapping'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_manager_date'),
      '#description' => $this->t('Map your fields like this: api_item_key|content_type_field, f.e. event_date|field_event_date. The sync will identify the type of date.')
    ];

    $form['api_manager_geolocation'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Geolocation mapping'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_manager_geolocation'),
      '#description' => $this->t('Map your fields like this: lat_key+long_key|content_type_field, f.e. location.lat+location.lng|field_location_geolocation.')
    ];

    $form['api_manager_weight'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Weight'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_manager_weight'),
      '#required' => TRUE,
      '#description' => $this->t('The order in which the API should run. f.e. always give terms a lower weights because nodes can refer to them.')
    ];

    $form['api_minutes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Interval'),
      '#maxlength' => 255,
      '#default_value' => $api_manager->get('api_minutes'),
      '#required' => TRUE,
      '#description' => $this->t('The minimum amount of minutes between every sync. Set to 0 to import every cron run. <strong>Set to "-" if you do not want to trigger the import on cron and thus only on external trigger.</strong>')
    ];

    $fieldset = array(
      'fieldset' => array(
        '#type' => 'fieldset',
        '#title' => t('External trigger'),
        'content' => array(
          '#markup' =>  $this->t('To trigger this api call from an external source, use the following link: @url',
            ['@url' => \Drupal::request()->getSchemeAndHttpHost() . '/admin/api/trigger/'.$api_manager->uuid()])
        ),
      ),
    );

    $form['external_trigger'] = [
      '#type' => 'markup',
      '#markup' => render($fieldset)
    ];

    $form['api_manager_active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active'),
      '#default_value' => ($api_manager->get('api_manager_active') === TRUE) ? $api_manager->get('api_manager_active') : 0,
      '#description' => $this->t('If disabled, items will never get imported.')
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $api_manager = $this->entity;
    $status = $api_manager->save();

    if ($status) {
      $this->messenger()->addMessage($this->t('Saved the %label API settings.', [
        '%label' => $api_manager->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label API settings could not be saved.', [
        '%label' => $api_manager->label(),
      ]), MessengerInterface::TYPE_ERROR);
    }

    $form_state->setRedirect('entity.api_manager.collection');
  }

  /**
   * Helper function to check whether an ApiManager configuration entity exists.
   */
  public function exist($id) {
    $entity = $this->entityTypeManager->getStorage('api_manager')->getQuery()
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

}
