<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Drupal\openintranet_notifications\Entity\NotificationInterface;

/**
 * Rolls a parent notification's status up from its delivery outcomes.
 *
 * The queue worker settles one delivery row at a time; this service recomputes
 * the parent notification's lifecycle status from ALL its delivery rows so the
 * parent reaches a terminal state (00-synteza §4.2). Without it the parent
 * lingers at 'queued' forever: retention never purges it, and the dashboard's
 * delivered/partial/failed filters stay empty.
 *
 * Roll-up rule, over the delivery statuses (pending|processing|sent|delivered|
 * failed|skipped|cancelled):
 * - any delivery still pending/processing → not all settled → leave as-is;
 * - all settled, at least one sent/delivered and none failed → 'delivered'
 *   (a 'skipped' channel is treated as a non-failure, so sent+skipped is still
 *   'delivered' and a skipped-only set yields no success → 'failed');
 * - all settled, some sent/delivered and some failed → 'partial';
 * - all settled with no sent/delivered (all failed/skipped/cancelled) →
 *   'failed'.
 */
final class NotificationStatusResolver {

  /**
   * Delivery statuses that mean the row has succeeded.
   */
  private const SUCCESS_STATUSES = ['sent', 'delivered'];

  /**
   * Delivery statuses that mean the row will see no further attempt.
   */
  private const SETTLED_STATUSES = ['sent', 'delivered', 'failed', 'skipped', 'cancelled'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Recomputes and persists the notification status from its delivery rows.
   *
   * A notification with no delivery rows is left untouched (its status is owned
   * by the dispatcher). The parent is saved only when the rolled-up status
   * differs from the persisted one.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface $notification
   *   The parent notification to roll up.
   */
  public function rollUpNotificationStatus(NotificationInterface $notification): void {
    $deliveryStorage = $this->entityTypeManager->getStorage('openintranet_notif_delivery');
    $ids = $deliveryStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('notification_id', $notification->id())
      ->execute();
    if ($ids === []) {
      return;
    }

    $statuses = [];
    foreach ($deliveryStorage->loadMultiple($ids) as $delivery) {
      \assert($delivery instanceof NotificationDeliveryInterface);
      $statuses[] = (string) $delivery->get('status')->value;
    }

    $rolled = $this->rollUp($statuses);
    if ($rolled === NULL) {
      return;
    }
    if ((string) $notification->get('status')->value !== $rolled) {
      $notification->set('status', $rolled);
      $notification->save();
    }
  }

  /**
   * Maps a set of delivery statuses to the parent notification status.
   *
   * @param string[] $statuses
   *   The delivery statuses of every row of one notification.
   *
   * @return string|null
   *   The rolled-up notification status, or NULL when not every delivery has
   *   settled yet (the parent stays queued until the rest finish).
   */
  private function rollUp(array $statuses): ?string {
    foreach ($statuses as $status) {
      if (!\in_array($status, self::SETTLED_STATUSES, TRUE)) {
        return NULL;
      }
    }

    $hasSuccess = FALSE;
    $hasFailure = FALSE;
    foreach ($statuses as $status) {
      if (\in_array($status, self::SUCCESS_STATUSES, TRUE)) {
        $hasSuccess = TRUE;
      }
      elseif ($status === 'failed') {
        $hasFailure = TRUE;
      }
    }

    if ($hasSuccess && $hasFailure) {
      return 'partial';
    }
    if ($hasSuccess) {
      return 'delivered';
    }
    return 'failed';
  }

}
