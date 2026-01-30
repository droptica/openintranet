<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Plugin\OiAccessEntity;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Interface for OI Access Entity plugins.
 */
interface OiAccessEntityPluginInterface extends PluginInspectionInterface {

  /**
   * Returns the entity type ID this plugin handles.
   *
   * @return string
   *   The entity type ID.
   */
  public function getEntityTypeId(): string;

  /**
   * Checks whether this plugin applies to the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if this plugin applies to the entity.
   */
  public function applies(EntityInterface $entity): bool;

  /**
   * Returns the route name for the access form.
   *
   * @return string
   *   The route name.
   */
  public function getAccessFormRoute(): string;

  /**
   * Returns the route parameters for the access form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The route parameters.
   */
  public function getAccessFormRouteParameters(EntityInterface $entity): array;

  /**
   * Builds the access form for an entity.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The modified form array.
   */
  public function buildAccessForm(array $form, FormStateInterface $form_state, EntityInterface $entity): array;

  /**
   * Handles access form submission.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  public function submitAccessForm(array &$form, FormStateInterface $form_state, EntityInterface $entity): void;

  /**
   * Performs additional access checks for this entity type.
   *
   * This can be used to implement inheritance logic (e.g., from folder to
   * document) or other entity-type-specific access rules.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $operation
   *   The operation (view, update, delete).
   *
   * @return bool|null
   *   TRUE to allow, FALSE to deny, NULL to defer to standard logic.
   */
  public function checkAccess(EntityInterface $entity, AccountInterface $account, string $operation): ?bool;

}
