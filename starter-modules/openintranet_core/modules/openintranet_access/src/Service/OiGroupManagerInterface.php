<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\openintranet_access\Entity\OiGroupInterface;

/**
 * Interface for the group manager service.
 */
interface OiGroupManagerInterface {

  /**
   * Gets all groups a user is directly a member of.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\openintranet_access\Entity\OiGroupInterface[]
   *   Array of groups the user is a member of.
   */
  public function getUserGroups(AccountInterface $account): array;

  /**
   * Gets all groups a user has access to (including ancestors).
   *
   * If user is in "Sales Wroclaw", this returns:
   * - Sales Wroclaw (direct membership)
   * - Branch Wroclaw (parent)
   * - Company ABC (grandparent)
   *
   * This is used for access checking - if content is restricted to
   * "Branch Wroclaw", users in child groups should have access.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\openintranet_access\Entity\OiGroupInterface[]
   *   Array of groups including ancestors.
   */
  public function getUserGroupsWithAncestors(AccountInterface $account): array;

  /**
   * Gets all descendant groups of a group (children, grandchildren, etc.).
   *
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $group
   *   The parent group.
   *
   * @return \Drupal\openintranet_access\Entity\OiGroupInterface[]
   *   Array of descendant groups.
   */
  public function getGroupDescendants(OiGroupInterface $group): array;

  /**
   * Gets all users who are members of a group.
   *
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $group
   *   The group.
   * @param bool $include_descendants
   *   Whether to include users from descendant groups.
   *
   * @return \Drupal\user\UserInterface[]
   *   Array of user entities.
   */
  public function getGroupMembers(OiGroupInterface $group, bool $include_descendants = TRUE): array;

  /**
   * Gets the count of members in a group.
   *
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $group
   *   The group.
   * @param bool $include_descendants
   *   Whether to include users from descendant groups.
   *
   * @return int
   *   The number of members.
   */
  public function getMemberCount(OiGroupInterface $group, bool $include_descendants = FALSE): int;

  /**
   * Adds a user to a group.
   *
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $group
   *   The group.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   */
  public function addMember(OiGroupInterface $group, AccountInterface $account): void;

  /**
   * Removes a user from a group.
   *
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $group
   *   The group.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   */
  public function removeMember(OiGroupInterface $group, AccountInterface $account): void;

  /**
   * Checks if a user is a member of a group.
   *
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $group
   *   The group.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param bool $check_ancestors
   *   Whether to check if user is member of any ancestor group.
   *
   * @return bool
   *   TRUE if the user is a member.
   */
  public function isMember(OiGroupInterface $group, AccountInterface $account, bool $check_ancestors = FALSE): bool;

  /**
   * Gets child groups of a parent group.
   *
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface|null $parent
   *   The parent group, or NULL for root groups.
   *
   * @return \Drupal\openintranet_access\Entity\OiGroupInterface[]
   *   Array of child groups.
   */
  public function getChildren(?OiGroupInterface $parent = NULL): array;

  /**
   * Checks if a group has child groups.
   *
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $group
   *   The group.
   *
   * @return bool
   *   TRUE if the group has children.
   */
  public function hasChildren(OiGroupInterface $group): bool;

  /**
   * Gets ancestor groups (parents, grandparents, etc.).
   *
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $group
   *   The group.
   *
   * @return \Drupal\openintranet_access\Entity\OiGroupInterface[]
   *   Array of ancestor groups, ordered from root to immediate parent.
   */
  public function getAncestors(OiGroupInterface $group): array;

}
