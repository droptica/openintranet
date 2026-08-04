<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\openintranet_access\Entity\OiGroupInterface;

/**
 * Service for managing OI groups and memberships.
 */
final class OiGroupManager implements OiGroupManagerInterface {

  /**
   * Maximum depth for hierarchy traversal to prevent infinite loops.
   */
  private const MAX_DEPTH = 50;

  /**
   * Constructs the group manager.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly Connection $database,
    protected readonly OiAuditLoggerInterface $auditLogger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getUserGroups(AccountInterface $account): array {
    $groupIds = $this->database->select('oi_group_membership', 'm')
      ->fields('m', ['group_id'])
      ->condition('user_id', (int) $account->id())
      ->execute()
      ->fetchCol();

    if (empty($groupIds)) {
      return [];
    }

    return $this->entityTypeManager
      ->getStorage('oi_group')
      ->loadMultiple($groupIds);
  }

  /**
   * {@inheritdoc}
   */
  public function getUserGroupsWithAncestors(AccountInterface $account): array {
    $groups = $this->getUserGroups($account);
    $result = [];

    foreach ($groups as $group) {
      // Add the group itself.
      $result[$group->id()] = $group;

      // Add all ancestors.
      foreach ($this->getAncestors($group) as $ancestor) {
        $result[$ancestor->id()] = $ancestor;
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupDescendants(OiGroupInterface $group): array {
    $descendants = [];
    $this->collectDescendants($group, $descendants, 0);
    return $descendants;
  }

  /**
   * Recursively collects descendant groups.
   *
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $group
   *   The parent group.
   * @param array $descendants
   *   Array to collect descendants into.
   * @param int $depth
   *   Current recursion depth.
   */
  protected function collectDescendants(OiGroupInterface $group, array &$descendants, int $depth): void {
    if ($depth >= self::MAX_DEPTH) {
      return;
    }

    $children = $this->getChildren($group);
    foreach ($children as $child) {
      $descendants[$child->id()] = $child;
      $this->collectDescendants($child, $descendants, $depth + 1);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupMembers(OiGroupInterface $group, bool $include_descendants = TRUE): array {
    $userIds = $this->database->select('oi_group_membership', 'm')
      ->fields('m', ['user_id'])
      ->condition('group_id', (int) $group->id())
      ->execute()
      ->fetchCol();

    if ($include_descendants) {
      $descendants = $this->getGroupDescendants($group);
      foreach ($descendants as $descendant) {
        $descendantUserIds = $this->database->select('oi_group_membership', 'm')
          ->fields('m', ['user_id'])
          ->condition('group_id', (int) $descendant->id())
          ->execute()
          ->fetchCol();
        $userIds = array_merge($userIds, $descendantUserIds);
      }
    }

    $userIds = array_unique($userIds);

    if (empty($userIds)) {
      return [];
    }

    return $this->entityTypeManager
      ->getStorage('user')
      ->loadMultiple($userIds);
  }

  /**
   * {@inheritdoc}
   */
  public function getMemberCount(OiGroupInterface $group, bool $include_descendants = FALSE): int {
    if (!$include_descendants) {
      return (int) $this->database->select('oi_group_membership', 'm')
        ->condition('group_id', (int) $group->id())
        ->countQuery()
        ->execute()
        ->fetchField();
    }

    // For descendants, we need to count unique users.
    $userIds = $this->database->select('oi_group_membership', 'm')
      ->fields('m', ['user_id'])
      ->condition('group_id', (int) $group->id())
      ->execute()
      ->fetchCol();

    $descendants = $this->getGroupDescendants($group);
    foreach ($descendants as $descendant) {
      $descendantUserIds = $this->database->select('oi_group_membership', 'm')
        ->fields('m', ['user_id'])
        ->condition('group_id', (int) $descendant->id())
        ->execute()
        ->fetchCol();
      $userIds = array_merge($userIds, $descendantUserIds);
    }

    return count(array_unique($userIds));
  }

  /**
   * {@inheritdoc}
   */
  public function addMember(OiGroupInterface $group, AccountInterface $account): void {
    // Check if already a member.
    $exists = $this->database->select('oi_group_membership', 'm')
      ->condition('group_id', (int) $group->id())
      ->condition('user_id', (int) $account->id())
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($exists) {
      return;
    }

    $this->database->insert('oi_group_membership')
      ->fields([
        'group_id' => (int) $group->id(),
        'user_id' => (int) $account->id(),
        'created' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    // Audit log.
    $this->auditLogger->logMemberAdded($group, $account);
  }

  /**
   * {@inheritdoc}
   */
  public function removeMember(OiGroupInterface $group, AccountInterface $account): void {
    // Check if member exists before logging.
    $exists = $this->database->select('oi_group_membership', 'm')
      ->condition('group_id', (int) $group->id())
      ->condition('user_id', (int) $account->id())
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->database->delete('oi_group_membership')
      ->condition('group_id', (int) $group->id())
      ->condition('user_id', (int) $account->id())
      ->execute();

    // Only log if member was actually removed.
    if ($exists) {
      $this->auditLogger->logMemberRemoved($group, $account);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isMember(OiGroupInterface $group, AccountInterface $account, bool $check_ancestors = FALSE): bool {
    $isMember = (bool) $this->database->select('oi_group_membership', 'm')
      ->condition('group_id', (int) $group->id())
      ->condition('user_id', (int) $account->id())
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($isMember || !$check_ancestors) {
      return $isMember;
    }

    // Check descendants (user in child group = member of parent for access).
    $descendants = $this->getGroupDescendants($group);
    foreach ($descendants as $descendant) {
      $isMemberOfDescendant = (bool) $this->database->select('oi_group_membership', 'm')
        ->condition('group_id', (int) $descendant->id())
        ->condition('user_id', (int) $account->id())
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($isMemberOfDescendant) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getChildren(?OiGroupInterface $parent = NULL): array {
    $query = $this->entityTypeManager->getStorage('oi_group')->getQuery();
    $query->accessCheck(TRUE);

    if ($parent === NULL) {
      $query->notExists('parent');
    }
    else {
      $query->condition('parent', $parent->id());
    }

    $ids = $query->execute();

    if (empty($ids)) {
      return [];
    }

    return $this->entityTypeManager
      ->getStorage('oi_group')
      ->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function hasChildren(OiGroupInterface $group): bool {
    $count = $this->entityTypeManager->getStorage('oi_group')->getQuery()
      ->accessCheck(TRUE)
      ->condition('parent', $group->id())
      ->count()
      ->execute();

    return $count > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getAncestors(OiGroupInterface $group): array {
    $ancestors = [];
    $parent = $group->getParent();
    $depth = 0;

    while ($parent !== NULL && $depth < self::MAX_DEPTH) {
      array_unshift($ancestors, $parent);
      $parent = $parent->getParent();
      $depth++;
    }

    return $ancestors;
  }

}
