<?php

namespace Drupal\api_manager\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\api_manager\ApiManager;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Provides a form for deleting a api_manager entity.
 *
 * @ingroup api_manager
 */
class ApiBulkdeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete imported items of API %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   *
   * If the Bulkdelete command is canceled, return to the api list.
   */
  public function getCancelUrl() {
    return new Url('entity.api_manager.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   *
   * Bulkdelete the entity and log the event. logger() replaces the watchdog.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();

    $id = $entity->id();
    $destination_entity = $entity->get('api_destination_entity');
    $destination_language = $entity->get('api_destination_language');
    $api_sync_field = $entity->get('api_sync_field');
    $entity_type = ApiManagerDetermineEntityType($destination_entity);

    $batch = [
      'title' => t('Deleting entities'),
      'operations' => [],
      'init_message' => t('Deletion process is starting.'),
      'progress_message' => t('Processed @current out of @total. Estimated time: @estimate.'),
      'error_message' => t('The process has encountered an error.')
    ];

    switch($entity_type) {
      case 'node':
        $nids = \Drupal::entityQuery('node')
          ->condition('langcode', $destination_language)
          ->condition($api_sync_field, $destination_entity . '_sync_', 'STARTS_WITH')
          ->execute();
        $count = 0;
        if ($nids && $entities = Node::loadMultiple($nids)) {
          foreach($entities as $entity) {
            $batch['operations'][] = [['\Drupal\api_manager\Form\ApiBulkDeleteForm', 'bulkDeleteItem'], [$entity]];
            $count++;
          }
        }
        \Drupal::messenger()->addMessage($count . ' imported nodes deleted.');
        break;
      case 'taxonomy':
        $tids = \Drupal::entityQuery('taxonomy_term')
          ->condition('vid', $destination_entity)
          ->condition($api_sync_field, '', '<>')
          ->execute();
        $count = 0;
        if ($tids && $entities = Term::loadMultiple($tids)) {
          foreach($entities as $entity) {
            $batch['operations'][] = [['\Drupal\api_manager\Form\ApiBulkDeleteForm', 'bulkDeleteItem'], [$entity]];
            $count++;
          }
        }
        \Drupal::messenger()->addMessage($count . ' imported terms deleted.');
        break;
    }

    if($count > 0) {
      batch_set($batch);
    }

    \Drupal::state()->delete('api_manager_' . $id);
    \Drupal::state()->set('api_manager_status_' . $id, 'ok');

    $this->logger('api_manager')->notice('@type: deleted '.$count.' %title imported items.',
      [
        '@type' => $this->entity->bundle(),
        '%title' => $this->entity->label(),
      ]);


    $form_state->setRedirect('entity.api_manager.collection');
  }

  /**
   * @param $entity
   * Deletes an entity
   */
  public function bulkDeleteItem($entity, &$context) {
    $context['results'][] = $entity->label();
    $context['message'] = t('Deleting @title', array('@title' => $entity->label()));
    $entity->delete();
  }

}
