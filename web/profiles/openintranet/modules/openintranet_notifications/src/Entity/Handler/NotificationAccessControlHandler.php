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
 * Access control for the openintranet_notification entity.
 *
 * A recipient may view and update (mark read/seen) their own notifications;
 * the "view notification logs" permission grants admin visibility. Creation
 * and deletion are reserved for that admin permission (the dispatcher runs as
 * the system actor in Stage 2).
 */
final class NotificationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    if ($account->hasPermission('view notification logs')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $uid = $entity instanceof FieldableEntityInterface ? $entity->get('uid')->target_id : NULL;
    // Anonymous (uid 0) must never match a notification whose recipient is
    // unset/0; ownership requires a real, equal account id.
    $is_owner = $account->id() > 0 && $uid !== NULL && (int) $uid === (int) $account->id();
    return match ($operation) {
      'view', 'update' => AccessResult::allowedIf($is_owner)
        ->cachePerUser()
        ->addCacheableDependency($entity),
      default => AccessResult::neutral()->cachePerPermissions(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'view notification logs');
  }

}
