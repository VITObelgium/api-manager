<?php

namespace Drupal\api_manager\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for deleting a api_manager entity.
 *
 * @ingroup api_manager
 */
class ApiDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete API %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   *
   * If the delete command is canceled, return to the api list.
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
   * Delete the entity and log the event. logger() replaces the watchdog.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $entity->delete();

    $this->logger('api_manager')->notice('@type: deleted %title.',
      [
        '@type' => $this->entity->bundle(),
        '%title' => $this->entity->label(),
      ]);
    $form_state->setRedirect('entity.api_manager.collection');
  }

}
