<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_access\Entity\OiGroupInterface;

/**
 * Form controller for the OI Group entity.
 */
class OiGroupForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    // Pre-fill parent from route parameter.
    $route_match = $this->getRouteMatch();
    $parent = $route_match->getParameter('parent');

    if ($parent instanceof OiGroupInterface && $this->entity->isNew()) {
      $form['parent']['widget'][0]['target_id']['#default_value'] = $parent;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    /** @var \Drupal\openintranet_access\Entity\OiGroupInterface $entity */
    $entity = $this->entity;

    // Check for children if trying to delete.
    if (!$entity->isNew()) {
      /** @var \Drupal\openintranet_access\Service\OiGroupManagerInterface $groupManager */
      $groupManager = \Drupal::service('openintranet_access.group_manager');

      if ($groupManager->hasChildren($entity)) {
        // Get the parent field value from form state.
        $parent_value = $form_state->getValue(['parent', 0, 'target_id']);
        $new_parent_id = $parent_value ? (int) $parent_value : NULL;
        $current_parent_id = $entity->getParentId();

        // Only show warning if parent is changing.
        if ($new_parent_id !== $current_parent_id) {
          $this->messenger()->addWarning($this->t('This group has child groups. Changing the parent will affect the hierarchy.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    /** @var \Drupal\openintranet_access\Entity\OiGroupInterface $entity */
    $entity = $this->entity;

    $message_args = ['%label' => $entity->getName()];
    $logger_args = [
      '%label' => $entity->label(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New group %label has been created.', $message_args));
        $this->logger('openintranet_access')->notice('New group %label has been created.', $logger_args);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The group %label has been updated.', $message_args));
        $this->logger('openintranet_access')->notice('The group %label has been updated.', $logger_args);
        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }

    $form_state->setRedirect('entity.oi_group.canonical', ['oi_group' => $entity->id()]);

    return $result;
  }

}
