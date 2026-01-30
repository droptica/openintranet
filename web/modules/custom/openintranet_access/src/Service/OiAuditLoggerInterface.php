<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\openintranet_access\Entity\OiGroupInterface;

/**
 * Interface for audit logging of access-related operations.
 */
interface OiAuditLoggerInterface {

  /**
   * Logs the creation of a group.
   *
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $group
   *   The created group.
   */
  public function logGroupCreated(OiGroupInterface $group): void;

  /**
   * Logs the deletion of a group.
   *
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $group
   *   The group being deleted.
   */
  public function logGroupDeleted(OiGroupInterface $group): void;

  /**
   * Logs adding a user to a group.
   *
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $group
   *   The group.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user being added.
   */
  public function logMemberAdded(OiGroupInterface $group, AccountInterface $user): void;

  /**
   * Logs removing a user from a group.
   *
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $group
   *   The group.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user being removed.
   */
  public function logMemberRemoved(OiGroupInterface $group, AccountInterface $user): void;

  /**
   * Logs adding a group to an entity's access list.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $group
   *   The group being granted access.
   */
  public function logEntityAccessGroupAdded(EntityInterface $entity, OiGroupInterface $group): void;

  /**
   * Logs removing a group from an entity's access list.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $group
   *   The group losing access.
   */
  public function logEntityAccessGroupRemoved(EntityInterface $entity, OiGroupInterface $group): void;

  /**
   * Logs adding a user to an entity's direct access list.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param int $userId
   *   The user ID being granted access.
   */
  public function logEntityAccessUserAdded(EntityInterface $entity, int $userId): void;

  /**
   * Logs removing a user from an entity's direct access list.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param int $userId
   *   The user ID losing access.
   */
  public function logEntityAccessUserRemoved(EntityInterface $entity, int $userId): void;

}
