<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Own-only access checker for the notification preference form (§14 security).
 *
 * A user may edit their OWN preferences when they hold "administer own
 * notification preferences"; "administer users" grants access to any profile.
 * Everyone else (including another logged-in user) is denied.
 */
final class NotificationPreferencesAccess implements ContainerInjectionInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static();
  }

  /**
   * Checks access to a user's notification preferences.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user whose preferences are being viewed (the route target).
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account requesting access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(UserInterface $user, AccountInterface $account): AccessResultInterface {
    // Ownership requires a real, equal account id so anonymous (uid 0) can
    // never match a target whose id is unexpectedly 0.
    $is_owner = $account->id() > 0 && (int) $account->id() === (int) $user->id();
    $allowed = ($is_owner && $account->hasPermission('administer own notification preferences'))
      || $account->hasPermission('administer users');

    return AccessResult::allowedIf($allowed)
      ->cachePerUser()
      ->cachePerPermissions()
      ->addCacheableDependency($user);
  }

}
