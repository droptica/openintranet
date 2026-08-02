<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\openintranet_access\Entity\OiGroupInterface;

/**
 * Interface for the access checker service.
 */
interface OiAccessCheckerInterface {

  /**
   * Checks if a user has access to an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check access for.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $operation
   *   The operation (view, update, delete).
   *
   * @return bool|null
   *   TRUE if access is granted, FALSE if denied, NULL if no restrictions.
   */
  public function checkEntityAccess(EntityInterface $entity, AccountInterface $account, string $operation): ?bool;

  /**
   * Checks if an entity has access restrictions.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if the entity has restrictions.
   */
  public function hasRestrictions(EntityInterface $entity): bool;

  /**
   * Gets groups that have access to an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\openintranet_access\Entity\OiGroupInterface[]
   *   Array of groups with access.
   */
  public function getAccessGroups(EntityInterface $entity): array;

  /**
   * Gets user IDs that have direct access to an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return int[]
   *   Array of user IDs.
   */
  public function getAccessUserIds(EntityInterface $entity): array;

  /**
   * Gets all user IDs with access to an entity (groups + direct).
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return int[]
   *   Array of unique user IDs.
   */
  public function getAllAllowedUserIds(EntityInterface $entity): array;

}
