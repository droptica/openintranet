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
   * A resolved window of 0 (or less) means "retain forever": such types are
   * excluded from purging entirely. The candidate query is bounded by the most
   * lenient positive window so a run never loads notifications too recent to be
   * expirable under any type, and by $limit when set.
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

    // Resolve the positive retention window per type up front. Types whose
    // window is <= 0 retain forever and are never purged; only positive windows
    // are eligible.
    $typeDays = [];
    foreach ($this->entityTypeManager->getStorage('openintranet_notification_type')->loadMultiple() as $id => $type) {
      \assert($type instanceof NotificationTypeInterface);
      $days = $this->retentionDaysForType($type, $defaultDays);
      if ($days > 0) {
        $typeDays[(string) $id] = $days;
      }
    }
    if ($typeDays === []) {
      return [];
    }

    // The smallest positive window is the most lenient cutoff: a notification
    // newer than it cannot be expired under any type, so it is never loaded.
    $minWindowSeconds = min($typeDays) * 86400;

    $storage = $this->entityTypeManager->getStorage('openintranet_notification');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', self::TERMINAL_STATUSES, 'IN')
      ->condition('created', $now - $minWindowSeconds, '<')
      ->sort('created', 'ASC');
    if ($limit !== NULL) {
      $query->range(0, $limit);
    }
    $candidateIds = $query->execute();
    if ($candidateIds === []) {
      return [];
    }

    $expired = [];
    foreach ($storage->loadMultiple($candidateIds) as $notification) {
      \assert($notification instanceof NotificationInterface);
      $type = (string) $notification->get('type')->value;
      // Types with no positive window were excluded above; skip their records.
      if (!isset($typeDays[$type])) {
        continue;
      }
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
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationTypeInterface $type
   *   The notification type entity.
   * @param int $defaultDays
   *   The global default retention window in days.
   *
   * @return int
   *   The type's own audit_retention_days when positive, otherwise the global
   *   default. A value of 0 (or less) means "retain forever".
   */
  private function retentionDaysForType(NotificationTypeInterface $type, int $defaultDays): int {
    $days = $type->getAuditRetentionDays();
    return $days > 0 ? $days : $defaultDays;
  }

}
