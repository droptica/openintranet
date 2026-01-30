<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\openintranet_access\Service\OiGroupManagerInterface;

/**
 * Cache context for user's OI group memberships.
 *
 * Cache context ID: 'user.oi_groups'.
 */
final class UserOiGroupsCacheContext implements CacheContextInterface {

  /**
   * Constructs the cache context.
   */
  public function __construct(
    protected readonly AccountInterface $currentUser,
    protected readonly OiGroupManagerInterface $groupManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getLabel(): string {
    return t("User's OI group memberships");
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): string {
    // Generate hash from user's group IDs (including ancestors).
    $groups = $this->groupManager->getUserGroupsWithAncestors($this->currentUser);
    $ids = array_map(fn($g) => (int) $g->id(), $groups);
    sort($ids);
    return hash('sha256', implode(',', $ids));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): CacheableMetadata {
    return (new CacheableMetadata())->setCacheTags(['oi_group_membership:' . $this->currentUser->id()]);
  }

}
