<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Channel\ChannelPluginManager;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Event\NotificationDeliveredEvent;
use Drupal\openintranet_notifications\Event\NotificationEvents;
use Drupal\openintranet_notifications\Event\NotificationFailedEvent;
use Drupal\openintranet_notifications\Event\NotificationPermanentlyFailedEvent;
use Drupal\openintranet_notifications\Service\AuditLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Delivers one queued delivery row on its channel, with retry and idempotency.
 *
 * The keystone of the framework (00-synteza §3.1): a custom worker is required
 * because the no-double-send guard, the error classification (retry vs.
 * permanent) and the backoff schedule must all live in one place — the channel
 * only classifies an outcome, it never decides whether to retry.
 */
#[QueueWorker(
  id: 'openintranet_notification_delivery',
  title: new TranslatableMarkup('Notification delivery'),
  cron: ['time' => 60],
)]
final class NotificationDeliveryWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The maximum number of send attempts before a failure becomes permanent.
   */
  private const MAX_ATTEMPTS = 5;

  /**
   * The base backoff in seconds, multiplied by the attempt count.
   */
  private const BACKOFF_BASE_SECONDS = 300;

  /**
   * The TTL of an in-flight send claim.
   *
   * Must be < BACKOFF_BASE_SECONDS so a legitimate retry (which only runs after
   * a >= 300s backoff) is never blocked by a stale claim, and >= the maximum
   * duration of a single send attempt so a slow send is not double-claimed.
   */
  private const CLAIM_TTL_SECONDS = 120;

  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ChannelPluginManager $channelManager,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
    private readonly AuditLogger $auditLogger,
    private readonly KeyValueExpirableFactoryInterface $keyValueExpirable,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.notification_channel'),
      $container->get('datetime.time'),
      $container->get('logger.channel.openintranet_notifications'),
      $container->get('openintranet_notifications.audit_logger'),
      $container->get('keyvalue.expirable'),
      $container->get('event_dispatcher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $deliveryId = $data['delivery_id'] ?? NULL;
    if ($deliveryId === NULL) {
      return;
    }

    /** @var \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface|null $delivery */
    $delivery = $this->entityTypeManager
      ->getStorage('openintranet_notif_delivery')
      ->load($deliveryId);
    if ($delivery === NULL) {
      return;
    }

    // Idempotency guard: a terminal delivery is never re-sent.
    if ($delivery->isTerminal()) {
      return;
    }

    // Backoff is enforced by re-queuing the SAME item with a delay: core's
    // Cron catches DelayedRequeueException and calls delayItem(), so the row's
    // own next_attempt/attempt_count survive and cron does not spin the window.
    $now = $this->time->getRequestTime();
    $nextAttempt = (int) $delivery->get('next_attempt')->value;
    if ($nextAttempt > $now) {
      throw new DelayedRequeueException(($nextAttempt - $now), 'Backoff: delivery not yet due.');
    }

    $channelId = (string) $delivery->get('channel')->value;
    if (!$this->channelManager->hasDefinition($channelId)) {
      $delivery->set('status', 'skipped');
      $delivery->save();
      return;
    }
    $channel = $this->channelManager->createInstance($channelId);
    if (!$channel->isAvailable()) {
      $delivery->set('status', 'skipped');
      $delivery->save();
      return;
    }

    $recipient = $this->buildRecipient($delivery);
    $message = $this->buildMessage($delivery);

    // Idempotency claim: atomically reserve this delivery before sending so two
    // overlapping cron runs (or a lease-expiry re-claim) cannot both send. The
    // claim is never released early; its TTL closes the window. A success makes
    // the row terminal (guarded above) and a retry only runs after a backoff
    // longer than the claim TTL, by when the claim has already expired.
    $store = $this->keyValueExpirable->get('openintranet_notifications.delivery_claim');
    $claimKey = (string) $delivery->get('idempotency_key')->value;
    if (!$store->setWithExpireIfNotExists($claimKey, $now, self::CLAIM_TTL_SECONDS)) {
      return;
    }

    $result = $channel->send($recipient, $message);
    $attemptCount = (int) $delivery->get('attempt_count')->value + 1;
    $delivery->set('attempt_count', $attemptCount);

    if ($result->success) {
      $delivery->markSent($result->providerMessageId);
      $this->auditLogger->log($delivery, $result);
      $delivery->save();
      $notification = $this->loadNotification($delivery);
      $this->eventDispatcher->dispatch(new NotificationDeliveredEvent($delivery, $notification), NotificationEvents::DELIVERED);
      return;
    }

    // Every failed attempt fires FAILED; PERMANENTLY_FAILED fires only when the
    // attempt becomes terminal (permanent error or exhausted retry budget).
    $notification = $this->loadNotification($delivery);
    $this->eventDispatcher->dispatch(new NotificationFailedEvent($delivery, $notification), NotificationEvents::FAILED);

    if ($result->retryable && $attemptCount < self::MAX_ATTEMPTS) {
      $delay = self::BACKOFF_BASE_SECONDS * $attemptCount;
      $delivery->scheduleRetry($delay);
      $this->auditLogger->log($delivery, $result);
      // Persist attempt_count/next_attempt/status BEFORE the throw so the next
      // pass sees the updated row; cron then delays the SAME queue item.
      $delivery->save();
      throw new DelayedRequeueException($delay, 'Retry backoff.');
    }

    // Permanent failure, or the retry budget is exhausted.
    $delivery->markFailed($result);
    $this->auditLogger->log($delivery, $result);
    $delivery->save();
    $this->eventDispatcher->dispatch(new NotificationPermanentlyFailedEvent($delivery, $notification), NotificationEvents::PERMANENTLY_FAILED);
  }

  /**
   * Loads the delivery's parent notification, if it still exists.
   */
  private function loadNotification(NotificationDeliveryInterface $delivery): ?NotificationInterface {
    $notificationId = $delivery->get('notification_id')->target_id;
    if ($notificationId === NULL) {
      return NULL;
    }
    /** @var \Drupal\openintranet_notifications\Entity\NotificationInterface|null $notification */
    $notification = $this->entityTypeManager
      ->getStorage('openintranet_notification')
      ->load($notificationId);
    return $notification;
  }

  /**
   * Builds the recipient DTO from the stored delivery row.
   */
  private function buildRecipient(NotificationDeliveryInterface $delivery): NotificationRecipient {
    $recipientType = (string) $delivery->get('recipient_type')->value;
    if ($recipientType === 'user') {
      $uid = (int) $delivery->get('recipient_id')->value;
      $user = $this->entityTypeManager->getStorage('user')->load($uid);
      return new NotificationRecipient(
        type: 'user',
        id: $uid,
        langcode: $user !== NULL ? $user->getPreferredLangcode() : 'en',
        account: $user,
      );
    }

    return new NotificationRecipient(
      type: $recipientType,
      value: (string) $delivery->get('contact_value')->value,
    );
  }

  /**
   * Builds the rendered message DTO from the parent notification.
   */
  private function buildMessage(NotificationDeliveryInterface $delivery): NotificationMessage {
    /** @var \Drupal\openintranet_notifications\Entity\NotificationInterface $notification */
    $notification = $this->entityTypeManager
      ->getStorage('openintranet_notification')
      ->load($delivery->get('notification_id')->target_id);
    \assert($notification instanceof NotificationInterface);

    $payload = $notification->get('payload')->first()?->getValue() ?? [];

    return new NotificationMessage(
      subject: (string) $notification->get('subject')->value,
      body: (string) $notification->get('body')->value,
      summary: (string) $notification->get('summary')->value,
      payload: $payload,
    );
  }

}
