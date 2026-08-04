<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\openintranet_access\Entity\OiGroupInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Service for checking entity access based on OI groups.
 */
final class OiAccessChecker implements OiAccessCheckerInterface {

  /**
   * Constructs the access checker.
   */
  public function __construct(
    protected readonly OiGroupManagerInterface $groupManager,
    protected readonly Connection $database,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function checkEntityAccess(EntityInterface $entity, AccountInterface $account, string $operation): ?bool {
    // Check if entity has restrictions.
    if (!$this->hasRestrictions($entity)) {
      return NULL;
    }

    $config = $this->configFactory->get('openintranet_access.settings');

    // Owner always has access (if configured).
    if ($config->get('behavior.owner_always_has_access')) {
      if ($entity instanceof EntityOwnerInterface && $entity->getOwnerId() === (int) $account->id()) {
        return TRUE;
      }
    }

    // Check direct user access.
    $allowedUserIds = $this->getAccessUserIds($entity);
    if (in_array((int) $account->id(), $allowedUserIds, TRUE)) {
      return TRUE;
    }

    // Check group access.
    $allowedGroups = $this->getAccessGroups($entity);
    if (empty($allowedGroups)) {
      // Has restrictions but no groups - only direct users have access.
      return FALSE;
    }

    // Get user's groups with ancestors for access checking.
    $userGroups = $this->groupManager->getUserGroupsWithAncestors($account);
    $userGroupIds = array_map(fn($g) => (int) $g->id(), $userGroups);

    foreach ($allowedGroups as $group) {
      // Check if user is in this group.
      if (in_array((int) $group->id(), $userGroupIds, TRUE)) {
        return TRUE;
      }

      // Check if user is in any descendant of allowed group.
      // This allows users in child groups to access parent-restricted content.
      $descendants = $this->groupManager->getGroupDescendants($group);
      foreach ($descendants as $descendant) {
        if (in_array((int) $descendant->id(), $userGroupIds, TRUE)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRestrictions(EntityInterface $entity): bool {
    if ($entity->isNew()) {
      return FALSE;
    }

    return (bool) $this->database->select('oi_access_record', 'oar')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', (int) $entity->id())
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessGroups(EntityInterface $entity): array {
    if ($entity->isNew()) {
      return [];
    }

    $groupIds = $this->database->select('oi_access_record', 'oar')
      ->fields('oar', ['target_id'])
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', (int) $entity->id())
      ->condition('grant_type', 'group')
      ->execute()
      ->fetchCol();

    if (empty($groupIds)) {
      return [];
    }

    $storage = \Drupal::entityTypeManager()->getStorage('oi_group');
    return $storage->loadMultiple($groupIds);
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessUserIds(EntityInterface $entity): array {
    if ($entity->isNew()) {
      return [];
    }

    $userIds = $this->database->select('oi_access_record', 'oar')
      ->fields('oar', ['target_id'])
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', (int) $entity->id())
      ->condition('grant_type', 'user')
      ->execute()
      ->fetchCol();

    return array_map('intval', $userIds);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllAllowedUserIds(EntityInterface $entity): array {
    $userIds = $this->getAccessUserIds($entity);

    // Get users from groups.
    $groups = $this->getAccessGroups($entity);
    foreach ($groups as $group) {
      $members = $this->groupManager->getGroupMembers($group, TRUE);
      foreach ($members as $member) {
        $userIds[] = (int) $member->id();
      }
    }

    return array_unique($userIds);
  }

}
