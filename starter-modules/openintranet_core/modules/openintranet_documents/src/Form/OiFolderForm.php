<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\openintranet_documents\OiFolderInterface;

/**
 * Form controller for the OI Folder entity.
 */
class OiFolderForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    // Attach library for proper autocomplete styling.
    $form['#attached']['library'][] = 'openintranet_documents/forms';

    // Pre-fill parent from route parameter.
    // Route: /documents/folder/{parent}/add
    $route_match = $this->getRouteMatch();
    $parent = $route_match->getParameter('parent');

    if ($parent instanceof OiFolderInterface && $this->entity->isNew()) {
      $form['parent']['widget'][0]['target_id']['#default_value'] = $parent;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    /** @var \Drupal\openintranet_documents\OiFolderInterface $entity */
    $entity = $this->entity;

    $message_args = ['%label' => $entity->getName()];
    $logger_args = [
      '%label' => $entity->label(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New folder %label has been created.', $message_args));
        $this->logger('openintranet_documents')->notice('New folder %label has been created.', $logger_args);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The folder %label has been updated.', $message_args));
        $this->logger('openintranet_documents')->notice('The folder %label has been updated.', $logger_args);
        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }

    // Redirect to parent folder listing or root.
    $parent = $entity->getParent();
    if ($parent instanceof OiFolderInterface) {
      $form_state->setRedirect('openintranet_documents.folder.view', ['oi_folder' => $parent->id()]);
    }
    else {
      $form_state->setRedirect('openintranet_documents.browser');
    }

    return $result;
  }

}
