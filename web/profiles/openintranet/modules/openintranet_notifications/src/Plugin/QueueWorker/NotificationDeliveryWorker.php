<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Channel\ChannelPluginManager;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Service\AuditLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ChannelPluginManager $channelManager,
    private readonly QueueFactory $queueFactory,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
    private readonly AuditLogger $auditLogger,
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
      $container->get('queue'),
      $container->get('datetime.time'),
      $container->get('logger.channel.openintranet_notifications'),
      $container->get('openintranet_notifications.audit_logger'),
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

    // Early defer: honor the backoff schedule without sending.
    // ponytail: core DatabaseQueue has no native delay; next_attempt is
    // enforced by this re-enqueue loop. Swap to advancedqueue for true delayed
    // delivery if cron churn becomes a problem.
    if ((int) $delivery->get('next_attempt')->value > $this->time->getRequestTime()) {
      $this->reEnqueue($deliveryId);
      return;
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

    $result = $channel->send($recipient, $message);
    $attemptCount = (int) $delivery->get('attempt_count')->value + 1;
    $delivery->set('attempt_count', $attemptCount);

    if ($result->success) {
      $delivery->markSent($result->providerMessageId);
    }
    elseif ($result->retryable && $attemptCount < self::MAX_ATTEMPTS) {
      $delivery->scheduleRetry(self::BACKOFF_BASE_SECONDS * $attemptCount);
      $this->reEnqueue($deliveryId);
    }
    else {
      // Permanent failure, or the retry budget is exhausted.
      $delivery->markFailed($result);
      // @todo Fire the ECA notification:permanently_failed event (Stage 2).
    }

    $this->auditLogger->log($delivery, $result);
    $delivery->save();
  }

  /**
   * Re-enqueues a delivery for a later processing pass.
   */
  private function reEnqueue(int|string $deliveryId): void {
    $this->queueFactory->get('openintranet_notification_delivery')
      ->createItem(['delivery_id' => $deliveryId]);
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
