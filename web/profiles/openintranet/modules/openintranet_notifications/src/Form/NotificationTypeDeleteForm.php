<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Confirm-and-delete form for the notification_type config entity.
 */
final class NotificationTypeDeleteForm extends EntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
  }

}
