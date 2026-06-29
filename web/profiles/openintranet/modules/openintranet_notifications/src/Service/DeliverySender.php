<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\openintranet_notifications\Channel\ChannelPluginManager;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Event\NotificationDeliveredEvent;
use Drupal\openintranet_notifications\Event\NotificationEvents;
use Drupal\openintranet_notifications\Event\NotificationFailedEvent;
use Drupal\openintranet_notifications\Event\NotificationPermanentlyFailedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Processes one delivery row on its channel, with idempotency and gates.
 *
 * The single home of the per-delivery send routine (00-synteza §3.1): the
 * no-double-send claim, the terminal-state and channel-availability guards, the
 * send, the retry-vs-permanent classification, the status roll-up and the
 * lifecycle events all live here. Both the queue worker (asynchronous, with
 * backoff and quiet-hours deferral around it) and the SendNow action
 * (synchronous test/small-send) call it, so neither can double-send or diverge.
 */
final class DeliverySender {

  /**
   * The default max send attempts when a channel declares no tighter cap.
   *
   * Mirrors NotificationChannelBase::DEFAULT_MAX_ATTEMPTS. The effective cap is
   * read per-delivery from the channel's maxAttempts() (00-synteza §3.1: "max
   * prób per kanał, np. 5"); this const is only the fallback used when the
   * channel is missing, and keeps the historical global behaviour.
   */
  public const MAX_ATTEMPTS = 5;

  /**
   * The base backoff in seconds, doubled on each successive attempt.
   *
   * The retry delay is EXPONENTIAL (00-synteza §3.1): for attempt N the delay
   * is BACKOFF_BASE_SECONDS * 2 ** (N - 1), i.e. 300, 600, 1200, 2400, … .
   */
  public const BACKOFF_BASE_SECONDS = 300;

  /**
   * The TTL of an in-flight send claim.
   *
   * Must be < BACKOFF_BASE_SECONDS so a legitimate retry (which only runs after
   * a >= 300s backoff) is never blocked by a stale claim, and >= the maximum
   * duration of a single send attempt so a slow send is not double-claimed.
   */
  public const CLAIM_TTL_SECONDS = 120;

  /**
   * The key-value collection holding in-flight send claims.
   */
  private const CLAIM_COLLECTION = 'openintranet_notifications.delivery_claim';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ChannelPluginManager $channelManager,
    private readonly TimeInterface $time,
    private readonly AuditLogger $auditLogger,
    private readonly KeyValueExpirableFactoryInterface $keyValueExpirable,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly NotificationStatusResolver $statusResolver,
    private readonly NotificationFactory $notificationFactory,
  ) {}

  /**
   * Sends one delivery on its channel, guarding against a double send.
   *
   * Idempotent and safe to call from any context: a terminal row, a missing or
   * unavailable channel and an already-claimed row all short-circuit without a
   * send. A retryable failure resets the row to pending with next_attempt set
   * and reports DeliveryOutcome::Retry so a queue caller can re-queue with
   * backoff; the row is never re-sent synchronously here.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $delivery
   *   The delivery to process; persisted in place with its outcome.
   *
   * @return \Drupal\openintranet_notifications\Service\DeliveryOutcome
   *   What happened, so the caller can react to a Retry.
   */
  public function send(NotificationDeliveryInterface $delivery): DeliveryOutcome {
    // Idempotency guard: a terminal delivery is never re-sent.
    if ($delivery->isTerminal()) {
      return DeliveryOutcome::NotSent;
    }

    $channelId = (string) $delivery->get('channel')->value;
    if (!$this->channelManager->hasDefinition($channelId)) {
      return $this->skip($delivery);
    }
    $channel = $this->channelManager->createInstance($channelId);
    if (!$channel->isAvailable()) {
      return $this->skip($delivery);
    }

    // Idempotency claim: atomically reserve this delivery before sending so two
    // overlapping runs (or a lease-expiry re-claim) cannot both send. The claim
    // is never released early; its TTL closes the window. A success makes the
    // row terminal (guarded above) and a retry only runs after a backoff longer
    // than the claim TTL, by when the claim has already expired.
    $store = $this->keyValueExpirable->get(self::CLAIM_COLLECTION);
    $claimKey = (string) $delivery->get('idempotency_key')->value;
    $now = $this->time->getRequestTime();
    if (!$store->setWithExpireIfNotExists($claimKey, $now, self::CLAIM_TTL_SECONDS)) {
      return DeliveryOutcome::NotSent;
    }

    // Mark the row in-flight before the channel call (00-synteza §4.3): the
    // claim is won, so this attempt owns the send. 'processing' is non-terminal
    // and still cancellable, so the no-double-send guard above is unaffected;
    // markSent/markFailed/skip below stamp the terminal outcome.
    $delivery->set('status', 'processing');
    $delivery->save();

    $recipient = $this->buildRecipient($delivery);
    $notification = $this->loadNotification($delivery);
    if ($notification === NULL) {
      // Notification deleted (retention/admin) since queueing; skip the orphan.
      return $this->skip($delivery);
    }
    $message = $this->buildMessage($notification, $channelId, $recipient);

    $result = $channel->send($recipient, $message);
    $attemptCount = (int) $delivery->get('attempt_count')->value + 1;
    $delivery->set('attempt_count', $attemptCount);

    if ($result->success) {
      $delivery->markSent($result->providerMessageId);
      $this->auditLogger->log($delivery, $result);
      $delivery->save();
      // Timed escalation cancel-on-success (00-synteza §3.2): this channel
      // landed, so cancel the notification's still-pending escalation tiers
      // that are not yet due — the email reached the user, the later SMS/push
      // is no longer needed. A permanent failure (below) does NOT cancel them,
      // so the escalation still fires when its delay elapses.
      $this->cancelNotYetDueSiblings($delivery);
      $notification = $this->loadNotification($delivery);
      if ($notification !== NULL) {
        $this->statusResolver->rollUpNotificationStatus($notification);
      }
      $this->eventDispatcher->dispatch(new NotificationDeliveredEvent($delivery, $notification), NotificationEvents::DELIVERED);
      return DeliveryOutcome::Sent;
    }

    // Every failed attempt fires FAILED; PERMANENTLY_FAILED fires only when the
    // attempt becomes terminal (permanent error or exhausted retry budget).
    $notification = $this->loadNotification($delivery);
    $this->eventDispatcher->dispatch(new NotificationFailedEvent($delivery, $notification), NotificationEvents::FAILED);

    // The retry budget is per-channel (00-synteza §3.1): the channel declares
    // its own cap (default 5), so a flaky transport can lower it without the
    // worker knowing the channel.
    $maxAttempts = $channel->maxAttempts();
    if ($result->retryable && $attemptCount < $maxAttempts) {
      // Exponential backoff: 300, 600, 1200, 2400, … (00-synteza §3.1).
      $delay = self::BACKOFF_BASE_SECONDS * (2 ** ($attemptCount - 1));
      $delivery->scheduleRetry($delay);
      $this->auditLogger->log($delivery, $result);
      // Persist attempt_count/next_attempt/status so a re-queue (or the next
      // pass) sees the updated row.
      $delivery->save();
      return DeliveryOutcome::Retry;
    }

    // Permanent failure, or the retry budget is exhausted.
    $delivery->markFailed($result);
    $this->auditLogger->log($delivery, $result);
    $delivery->save();
    if ($notification !== NULL) {
      $this->statusResolver->rollUpNotificationStatus($notification);
    }
    $this->eventDispatcher->dispatch(new NotificationPermanentlyFailedEvent($delivery, $notification), NotificationEvents::PERMANENTLY_FAILED);
    return DeliveryOutcome::PermanentlyFailed;
  }

  /**
   * Cancels a notification's not-yet-due pending escalation tiers.
   *
   * Called after a successful send (00-synteza §3.2 cancel-on-success): the
   * sibling deliveries of the SAME notification that are still 'pending' with a
   * future next_attempt are the escalation tiers that have not fired yet (e.g.
   * the SMS held 10 minutes behind a delivered email). They are stamped
   * 'cancelled' so they never send. A pending sibling already due (next_attempt
   * <= now) is left alone — it is mid-flight in the queue, not a held tier —
   * and the just-sent delivery itself is excluded.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $sentDelivery
   *   The delivery that just succeeded.
   */
  private function cancelNotYetDueSiblings(NotificationDeliveryInterface $sentDelivery): void {
    $notificationId = $sentDelivery->get('notification_id')->target_id;
    if ($notificationId === NULL) {
      return;
    }

    $now = $this->time->getRequestTime();
    $storage = $this->entityTypeManager->getStorage('openintranet_notif_delivery');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('notification_id', $notificationId)
      ->condition('status', 'pending')
      ->condition('next_attempt', $now, '>')
      ->condition('id', $sentDelivery->id(), '<>')
      ->execute();
    if ($ids === []) {
      return;
    }

    foreach ($storage->loadMultiple($ids) as $sibling) {
      \assert($sibling instanceof NotificationDeliveryInterface);
      $sibling->set('status', 'cancelled');
      $sibling->save();
    }
  }

  /**
   * Marks a delivery skipped (missing/unavailable channel) and rolls up.
   */
  private function skip(NotificationDeliveryInterface $delivery): DeliveryOutcome {
    $delivery->set('status', 'skipped');
    $delivery->save();
    $notification = $this->loadNotification($delivery);
    if ($notification !== NULL) {
      $this->statusResolver->rollUpNotificationStatus($notification);
    }
    return DeliveryOutcome::NotSent;
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
      return NotificationRecipient::forUserId($uid, $user);
    }

    return new NotificationRecipient(
      type: $recipientType,
      value: (string) $delivery->get('contact_value')->value,
    );
  }

  /**
   * Builds the message DTO for a delivery, applying any per-channel template.
   *
   * The default message is the channel-agnostic body the factory rendered once
   * and stored on the notification. When the type maps THIS channel to its own
   * template (00-synteza §4.1), the channel-specific template is rendered at
   * send time and overrides the stored subject/body/summary, so a per-channel
   * template_map entry genuinely changes what that channel sends. A channel
   * with no map entry keeps the stored default.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface $notification
   *   The delivery's parent notification (already loaded by the caller).
   * @param string $channelId
   *   The target channel plugin id.
   * @param \Drupal\openintranet_notifications\Dto\NotificationRecipient $recipient
   *   The recipient (langcode drives the channel-specific render language).
   *
   * @return \Drupal\openintranet_notifications\Dto\NotificationMessage
   *   The message to send on the channel.
   */
  private function buildMessage(NotificationInterface $notification, string $channelId, NotificationRecipient $recipient): NotificationMessage {
    $payload = $notification->get('payload')->first()?->getValue() ?? [];

    // Per-channel template_map (§4.1): if the type maps this channel, render
    // the channel-specific template at send time instead of the stored default.
    $type = $this->entityTypeManager
      ->getStorage('openintranet_notification_type')
      ->load((string) $notification->get('type')->value);
    if ($type instanceof NotificationTypeInterface && isset($type->getTemplateMap()[$channelId])) {
      $rendered = $this->notificationFactory->renderForChannel($notification, $type, $channelId, $recipient);
      return new NotificationMessage(
        subject: $rendered->subject,
        body: $rendered->body,
        summary: $rendered->summary,
        payload: $payload,
      );
    }

    return new NotificationMessage(
      subject: (string) $notification->get('subject')->value,
      body: (string) $notification->get('body')->value,
      summary: (string) $notification->get('summary')->value,
      payload: $payload,
    );
  }

}
