<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ChannelPluginManager $channelManager,
    private readonly QueueFactory $queueFactory,
    private readonly ConfigFactoryInterface $configFactory,
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
   *
   * @return array<int, int|string>
   *   The created delivery entity ids.
   */
  public function createAndEnqueue(NotificationInterface $notification, NotificationRecipient $recipient, array $channelIds): array {
    $queueId = $this->configFactory->get('openintranet_notifications.settings')->get('queue.id');
    $queue = $this->queueFactory->get($queueId);

    $ids = [];
    foreach ($channelIds as $channelId) {
      $delivery = $this->createDeliveryRow($notification, $recipient, $channelId);
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

}
