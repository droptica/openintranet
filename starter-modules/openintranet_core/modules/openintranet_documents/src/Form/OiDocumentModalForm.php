<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Modal form controller for the OI Document entity.
 */
final class OiDocumentModalForm extends OiDocumentForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    // Add class to the modal form.
    $form['#attributes']['class'][] = 'oi-document-modal-form';

    // Add AJAX submit handler.
    $form['actions']['submit']['#ajax'] = [
      'callback' => '::ajaxSubmit',
      'event' => 'click',
    ];

    // Attach dialog library.
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * AJAX callback for form submission.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    // Check for validation errors.
    if ($form_state->hasAnyErrors()) {
      // Show each specific error message.
      $errors = $form_state->getErrors();
      foreach ($errors as $field => $error) {
        $response->addCommand(new MessageCommand(
          $error,
          NULL,
          ['type' => 'error'],
        ));
      }
      // If no specific errors, show generic message.
      if (empty($errors)) {
        $response->addCommand(new MessageCommand(
          $this->t('Please correct the errors in the form.'),
          NULL,
          ['type' => 'error'],
        ));
      }
      return $response;
    }

    // Close the modal.
    $response->addCommand(new CloseModalDialogCommand());

    // Add success message.
    /** @var \Drupal\openintranet_documents\OiDocumentInterface $entity */
    $entity = $this->entity;
    $response->addCommand(new MessageCommand(
      $this->t('Document %label has been saved.', ['%label' => $entity->getTitle()]),
      NULL,
      ['type' => 'status'],
    ));

    // Redirect to the appropriate folder.
    $folder = $entity->getFolder();
    if ($folder) {
      $url = Url::fromRoute('openintranet_documents.folder.view', ['oi_folder' => $folder->id()])->toString();
    }
    else {
      $url = Url::fromRoute('openintranet_documents.browser')->toString();
    }
    $response->addCommand(new RedirectCommand($url));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    // Don't redirect - AJAX handler will handle it.
    $form_state->disableRedirect();

    return $result;
  }

}
