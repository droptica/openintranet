<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Event\NotificationDigestReadyEvent;
use Drupal\openintranet_notifications\Event\NotificationEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Aggregates not-yet-digested digest_only notifications into per-user digests.
 *
 * Candidates are notifications whose type uses the digest_only delivery policy
 * and that have not yet been marked digested. They are grouped by recipient;
 * for each recipient with at least one pending item the builder fires
 * NotificationDigestReadyEvent so an ECA model or integrator can render and
 * send the aggregated digest, then marks every item in that group digested so
 * a re-run never includes it again (00-synteza §3.2, Stage 6).
 */
final class DigestBuilder {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Builds and dispatches digests for pending digest_only notifications.
   *
   * @param int|null $limit
   *   The maximum number of candidate notifications to consider in this run
   *   (oldest first), or NULL for no cap.
   *
   * @return int
   *   The number of users a digest was dispatched for in this run.
   */
  public function buildAndDispatch(?int $limit = NULL): int {
    $digestTypeIds = $this->digestOnlyTypeIds();
    if ($digestTypeIds === []) {
      return 0;
    }

    $storage = $this->entityTypeManager->getStorage('openintranet_notification');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $digestTypeIds, 'IN')
      ->notExists('digested')
      ->sort('created', 'ASC')
      ->sort('id', 'ASC');
    if ($limit !== NULL) {
      $query->range(0, $limit);
    }
    $ids = $query->execute();
    if ($ids === []) {
      return 0;
    }

    // Group the pending notifications by recipient uid.
    $byUid = [];
    foreach ($storage->loadMultiple($ids) as $notification) {
      \assert($notification instanceof NotificationInterface);
      $uid = (int) $notification->get('uid')->target_id;
      $byUid[$uid][] = $notification;
    }

    foreach ($byUid as $uid => $notifications) {
      $this->eventDispatcher->dispatch(new NotificationDigestReadyEvent($uid), NotificationEvents::DIGEST_READY);
      foreach ($notifications as $notification) {
        $notification->markDigested();
        $notification->save();
      }
    }

    return count($byUid);
  }

  /**
   * Resolves the ids of notification types using the digest_only policy.
   *
   * @return string[]
   *   The matching notification_type machine names.
   */
  private function digestOnlyTypeIds(): array {
    $ids = [];
    foreach ($this->entityTypeManager->getStorage('openintranet_notification_type')->loadMultiple() as $id => $type) {
      \assert($type instanceof NotificationTypeInterface);
      if ($type->getDeliveryPolicy() === 'digest_only') {
        $ids[] = (string) $id;
      }
    }
    return $ids;
  }

}
