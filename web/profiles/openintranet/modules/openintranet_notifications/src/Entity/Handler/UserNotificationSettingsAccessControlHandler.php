<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Entity\Handler;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control for the user_notification_settings entity.
 *
 * A user manages their own preferences (the "administer own notification
 * preferences" permission gates the matrix); the log permission grants admin
 * visibility (00-synteza §4.4).
 */
final class UserNotificationSettingsAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    if ($account->hasPermission('view notification logs')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $is_owner = $entity instanceof FieldableEntityInterface
      && (int) $entity->get('uid')->target_id === (int) $account->id();
    $own = $is_owner && $account->hasPermission('administer own notification preferences');

    return match ($operation) {
      'view', 'update', 'delete' => AccessResult::allowedIf($own)
        ->cachePerUser()
        ->cachePerPermissions()
        ->addCacheableDependency($entity),
      default => AccessResult::neutral()->cachePerPermissions(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'administer own notification preferences');
  }

}
