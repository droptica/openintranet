<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_documents\OiFolderInterface;

/**
 * Form controller for the OI Document entity.
 */
class OiDocumentForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    // Attach library for proper autocomplete styling.
    $form['#attached']['library'][] = 'openintranet_documents/forms';

    // Pre-fill folder from route parameter.
    // Route: /documents/folder/{folder}/add-document
    $route_match = $this->getRouteMatch();
    $folder = $route_match->getParameter('folder');

    if ($folder instanceof OiFolderInterface && $this->entity->isNew()) {
      $form['folder']['widget'][0]['target_id']['#default_value'] = $folder;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    /** @var \Drupal\openintranet_documents\OiDocumentInterface $entity */
    $entity = $this->entity;

    $message_args = ['%label' => $entity->getTitle()];
    $logger_args = [
      '%label' => $entity->label(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New document %label has been created.', $message_args));
        $this->logger('openintranet_documents')->notice('New document %label has been created.', $logger_args);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The document %label has been updated.', $message_args));
        $this->logger('openintranet_documents')->notice('The document %label has been updated.', $logger_args);
        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }

    // Redirect to folder listing where the document is located.
    $folder = $entity->getFolder();
    if ($folder) {
      $form_state->setRedirect('openintranet_documents.folder.view', ['oi_folder' => $folder->id()]);
    }
    else {
      $form_state->setRedirect('openintranet_documents.browser');
    }

    return $result;
  }

}
