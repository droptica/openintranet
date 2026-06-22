<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;

/**
 * Enforces notification log retention by purging expired audit records.
 *
 * A notification is purged once it is terminal (no further delivery work) AND
 * older than its retention window: the notification type's audit_retention_days
 * when set, otherwise the settings default (00-synteza §9). Deleting a
 * notification cascade-deletes its delivery rows so no orphan audit data
 * lingers.
 */
final class RetentionPurger {

  /**
   * Statuses past which a notification will see no further delivery work.
   */
  private const TERMINAL_STATUSES = ['delivered', 'partial', 'failed', 'cancelled'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Deletes expired terminal notifications and their deliveries.
   *
   * @param int|null $limit
   *   The maximum number of notifications to purge in this run, or NULL for no
   *   cap.
   *
   * @return int
   *   The number of notifications deleted.
   */
  public function purge(?int $limit = NULL): int {
    $ids = $this->expiredNotificationIds($limit);
    if ($ids === []) {
      return 0;
    }

    $notificationStorage = $this->entityTypeManager->getStorage('openintranet_notification');
    $deliveryStorage = $this->entityTypeManager->getStorage('openintranet_notif_delivery');

    $deliveryIds = $deliveryStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('notification_id', $ids, 'IN')
      ->execute();
    if ($deliveryIds !== []) {
      $deliveryStorage->delete($deliveryStorage->loadMultiple($deliveryIds));
    }

    $notifications = $notificationStorage->loadMultiple($ids);
    $notificationStorage->delete($notifications);

    return count($notifications);
  }

  /**
   * Resolves the ids of notifications past their retention window.
   *
   * @param int|null $limit
   *   The maximum number of ids to return, or NULL for no cap.
   *
   * @return array<int, int|string>
   *   The expired notification entity ids.
   *
   * @todo Group candidates by type and apply one range query per window instead
   *   of evaluating the cutoff per entity once the volume warrants it.
   */
  private function expiredNotificationIds(?int $limit): array {
    $now = $this->time->getRequestTime();
    $defaultDays = (int) ($this->configFactory->get('openintranet_notifications.settings')
      ->get('retention.default_days') ?? 90);

    $storage = $this->entityTypeManager->getStorage('openintranet_notification');
    $candidateIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', self::TERMINAL_STATUSES, 'IN')
      ->sort('created', 'ASC')
      ->execute();
    if ($candidateIds === []) {
      return [];
    }

    $typeDays = [];
    $expired = [];
    foreach ($storage->loadMultiple($candidateIds) as $notification) {
      \assert($notification instanceof NotificationInterface);
      $type = (string) $notification->get('type')->value;
      $typeDays[$type] ??= $this->retentionDaysForType($type, $defaultDays);
      $created = (int) $notification->get('created')->value;
      if ($now - $created > $typeDays[$type] * 86400) {
        $expired[] = $notification->id();
        if ($limit !== NULL && count($expired) >= $limit) {
          break;
        }
      }
    }

    return $expired;
  }

  /**
   * Resolves the retention window in days for a notification type.
   */
  private function retentionDaysForType(string $type, int $defaultDays): int {
    $entity = $this->entityTypeManager->getStorage('openintranet_notification_type')->load($type);
    if ($entity instanceof NotificationTypeInterface) {
      $days = $entity->getAuditRetentionDays();
      if ($days > 0) {
        return $days;
      }
    }
    return $defaultDays;
  }

}
