<?php

namespace Drupal\api_manager\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\api_manager\ApiManager;

/**
 * Provides a form for deleting a api_manager entity.
 *
 * @ingroup api_manager
 */
class ApiImportForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to import %name items?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   *
   * If the import command is canceled, return to the api list.
   */
  public function getCancelUrl() {
    return new Url('entity.api_manager.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Import');
  }

  /**
   * {@inheritdoc}
   *
   * Import the entity and log the event. logger() replaces the watchdog.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();

    $import = new ApiManager;
    $import->startJobs($entity->id());

    $this->logger('api_manager')->notice('@type: imported %title items.',
      [
        '@type' => $this->entity->bundle(),
        '%title' => $this->entity->label(),
      ]);
    $form_state->setRedirect('entity.api_manager.collection');
  }

}
