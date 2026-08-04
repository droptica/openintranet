<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\openintranet_access\Entity\OiGroupInterface;

/**
 * Interface for the access manager service.
 */
interface OiAccessManagerInterface {

  /**
   * Sets the groups that have access to an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface[] $groups
   *   Array of groups to grant access.
   */
  public function setAccessGroups(EntityInterface $entity, array $groups): void;

  /**
   * Adds a group to the entity's access list.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $group
   *   The group to add.
   */
  public function addAccessGroup(EntityInterface $entity, OiGroupInterface $group): void;

  /**
   * Removes a group from the entity's access list.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $group
   *   The group to remove.
   */
  public function removeAccessGroup(EntityInterface $entity, OiGroupInterface $group): void;

  /**
   * Sets the users that have direct access to an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param int[] $user_ids
   *   Array of user IDs.
   */
  public function setAccessUsers(EntityInterface $entity, array $user_ids): void;

  /**
   * Adds a user to the entity's access list.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param int $user_id
   *   The user ID.
   */
  public function addAccessUser(EntityInterface $entity, int $user_id): void;

  /**
   * Removes a user from the entity's access list.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param int $user_id
   *   The user ID.
   */
  public function removeAccessUser(EntityInterface $entity, int $user_id): void;

  /**
   * Clears all access restrictions from an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  public function clearAccessRestrictions(EntityInterface $entity): void;

  /**
   * Copies access settings from one entity to another.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source
   *   The source entity.
   * @param \Drupal\Core\Entity\EntityInterface $target
   *   The target entity.
   */
  public function copyAccessSettings(EntityInterface $source, EntityInterface $target): void;

}
