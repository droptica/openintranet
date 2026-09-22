<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Checks access to entity access form routes.
 */
final class EntityAccessCheck implements AccessInterface {

  /**
   * Constructs the access check.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Checks access to the entity access form.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    // Check basic permission.
    if (!$account->hasPermission('set entity access')) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    // Check if entity type is enabled.
    $node = $route_match->getParameter('node');
    if ($node instanceof NodeInterface) {
      $config = $this->configFactory->get('openintranet_access.settings');
      $enabled_types = $config->get('enabled_entity_types.node') ?? [];

      // If empty, all types are enabled.
      if (!empty($enabled_types) && !in_array($node->bundle(), $enabled_types, TRUE)) {
        return AccessResult::forbidden('Content type not enabled for access control.')
          ->addCacheableDependency($config);
      }

      return AccessResult::allowed()
        ->addCacheableDependency($node)
        ->addCacheableDependency($config)
        ->cachePerPermissions();
    }

    // For other entity types.
    return AccessResult::allowed()->cachePerPermissions();
  }

}
