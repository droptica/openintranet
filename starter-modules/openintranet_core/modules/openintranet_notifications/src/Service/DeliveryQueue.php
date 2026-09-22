<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\openintranet_notifications\Channel\ChannelPluginManager;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Drupal\openintranet_notifications\Entity\NotificationInterface;

/**
 * Materializes a notification into per-channel delivery rows and enqueues them.
 *
 * Each delivery is one recipient × one channel attempt (00-synteza §4.3): the
 * channel resolves its own address (§8), the row carries an idempotency key so
 * the worker never double-sends, and one queue item per row drives the worker.
 */
final class DeliveryQueue {

  /**
   * The key-value collection holding in-flight send claims.
   *
   * Mirrors DeliverySender::CLAIM_COLLECTION; requeue() clears the claim so a
   * retry can re-claim and send.
   */
  private const CLAIM_COLLECTION = 'openintranet_notifications.delivery_claim';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ChannelPluginManager $channelManager,
    private readonly QueueFactory $queueFactory,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly KeyValueExpirableFactoryInterface $keyValueExpirable,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Creates a delivery per channel and enqueues a work item for each.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface $notification
   *   The saved notification to deliver.
   * @param \Drupal\openintranet_notifications\Dto\NotificationRecipient $recipient
   *   The recipient identity.
   * @param string[] $channelIds
   *   The channel plugin ids to deliver on.
   * @param array<string, int> $delays
   *   Optional per-channel timed-escalation delays (00-synteza §3.2): a channel
   *   id => seconds map. Each row's initial next_attempt is set to now + the
   *   channel's delay (default 0 = immediate), so the worker's early-defer
   *   holds the later tier back until it is due, with no extra queue backend.
   *
   * @return array<int, int|string>
   *   The created delivery entity ids.
   */
  public function createAndEnqueue(NotificationInterface $notification, NotificationRecipient $recipient, array $channelIds, array $delays = []): array {
    $queueId = $this->configFactory->get('openintranet_notifications.settings')->get('queue.id');
    $queue = $this->queueFactory->get($queueId);
    $now = $this->time->getRequestTime();

    $ids = [];
    foreach ($channelIds as $channelId) {
      $delivery = $this->createDeliveryRow($notification, $recipient, $channelId);
      $delay = max(0, (int) ($delays[$channelId] ?? 0));
      if ($delay > 0) {
        $delivery->set('next_attempt', $now + $delay);
      }
      $delivery->save();
      $queue->createItem(['delivery_id' => $delivery->id()]);
      $ids[] = $delivery->id();
    }

    return $ids;
  }

  /**
   * Builds a single unsaved delivery row for one recipient × one channel.
   *
   * The single home of the idempotency-key formula and the pending-row shape.
   * The caller persists it (and enqueues or sends) — this method does neither.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface $notification
   *   The notification being delivered.
   * @param \Drupal\openintranet_notifications\Dto\NotificationRecipient $recipient
   *   The recipient identity.
   * @param string $channelId
   *   The channel plugin id.
   *
   * @return \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface
   *   The unsaved, pending delivery row.
   */
  public function createDeliveryRow(NotificationInterface $notification, NotificationRecipient $recipient, string $channelId): NotificationDeliveryInterface {
    $channel = $this->channelManager->createInstance($channelId);
    $recipientRef = (string) ($recipient->id ?? $recipient->value);

    /** @var \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $delivery */
    $delivery = $this->entityTypeManager->getStorage('openintranet_notif_delivery')->create([
      'notification_id' => $notification->id(),
      'recipient_type' => $recipient->type,
      'recipient_id' => $recipient->id,
      'contact_value' => $recipient->value,
      'channel' => $channelId,
      'address' => $channel->getRecipientAddress($recipient),
      'status' => 'pending',
      'idempotency_key' => hash('sha256', $notification->id() . ':' . $recipientRef . ':' . $channelId),
    ]);
    return $delivery;
  }

  /**
   * Resets a delivery for a fresh attempt and re-enqueues a work item.
   *
   * The single home of the requeue logic so the bulk-ops form, the drush
   * retry command, and any future caller all reset and enqueue identically
   * (00-synteza §7/§16 DRY).
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $delivery
   *   The delivery to retry; status is reset to pending and re-queued.
   */
  public function requeue(NotificationDeliveryInterface $delivery): void {
    $delivery->set('status', 'pending');
    $delivery->set('next_attempt', 0);
    $delivery->set('attempt_count', 0);
    $delivery->save();

    // Clear any in-flight idempotency claim from the just-failed attempt: its
    // TTL may not have expired, and the shared sender's claim guard would
    // otherwise swallow this immediate retry without ever sending.
    $claimKey = (string) $delivery->get('idempotency_key')->value;
    if ($claimKey !== '') {
      $this->keyValueExpirable->get(self::CLAIM_COLLECTION)->delete($claimKey);
    }

    $queueId = $this->configFactory->get('openintranet_notifications.settings')->get('queue.id');
    $this->queueFactory->get($queueId)->createItem(['delivery_id' => $delivery->id()]);
  }

  /**
   * Cancels a single delivery so the worker skips it.
   *
   * The single home of the delivery-cancel logic, shared by the bulk-ops form
   * and the cancel ECA action so there is one implementation, not two.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $delivery
   *   The delivery to cancel; status is set to cancelled and saved.
   */
  public function cancel(NotificationDeliveryInterface $delivery): void {
    $delivery->set('status', 'cancelled');
    $delivery->save();
  }

}
