<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Own-only access checker for the single-notification view (§14 security).
 *
 * A user may view their OWN notification; "view notification logs" admins may
 * view any. Anonymous and other logged-in users are denied. Aligns with the
 * Stage-4 NotificationAccessControlHandler ownership rule.
 */
final class NotificationViewAccess implements ContainerInjectionInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static();
  }

  /**
   * Checks access to a single notification.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account requesting access.
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface|null $openintranet_notification
   *   The notification being viewed (the upcast route parameter).
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, ?NotificationInterface $openintranet_notification = NULL): AccessResultInterface {
    if (!$openintranet_notification instanceof NotificationInterface) {
      return AccessResult::forbidden()->cachePerUser();
    }

    if ($account->hasPermission('view notification logs')) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($openintranet_notification);
    }

    // Ownership requires a real, equal account id so anonymous (uid 0) can
    // never match a recipient-less notification.
    $uid = $openintranet_notification->get('uid')->target_id;
    $is_owner = $account->id() > 0 && $uid !== NULL && (int) $uid === (int) $account->id();

    return AccessResult::allowedIf($is_owner)
      ->cachePerUser()
      ->addCacheableDependency($openintranet_notification);
  }

}
