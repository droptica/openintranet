<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Channel\ChannelPluginManager;
use Drupal\openintranet_notifications\QuietHours;
use Drupal\openintranet_notifications\Service\DeliveryOutcome;
use Drupal\openintranet_notifications\Service\DeliverySender;
use Drupal\openintranet_notifications\Service\PreferenceResolverInterface;
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
   * Channels that send regardless of recipient quiet hours.
   *
   * On-site / dev channels are never deferred: the inbox is read on demand and
   * log_only/null have no recipient-facing transport. Every other channel is a
   * deferrable transport.
   *
   * @todo replace the allowlist with a per-channel "respects quiet hours" flag
   *   once channels can declare it on their plugin definition.
   */
  private const QUIET_HOURS_EXEMPT_CHANNELS = ['inbox', 'log_only', 'null'];

  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ChannelPluginManager $channelManager,
    private readonly TimeInterface $time,
    private readonly PreferenceResolverInterface $preferenceResolver,
    private readonly DeliverySender $deliverySender,
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
      $container->get('openintranet_notifications.preference_resolver'),
      $container->get('openintranet_notifications.delivery_sender'),
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

    // Quiet-hours deferral: a deferrable transport send to a user recipient who
    // is currently within their quiet window is re-queued until the window ends
    // (same DelayedRequeueException mechanism as the backoff defer above, so
    // the row's status/next_attempt survive and cron does not spin). Exempt
    // on-site/dev channels and non-user recipients (no uid → no quiet hours).
    // This runs only for channels that exist; the shared sender skips the rest.
    $channelId = (string) $delivery->get('channel')->value;
    if ($this->channelManager->hasDefinition($channelId)
      && !\in_array($channelId, self::QUIET_HOURS_EXEMPT_CHANNELS, TRUE)
      && (string) $delivery->get('recipient_type')->value === 'user') {
      $uid = (int) $delivery->get('recipient_id')->value;
      $quietHours = $this->preferenceResolver->getQuietHours($uid);
      if (!empty($quietHours) && QuietHours::isWithin($quietHours, $now)) {
        $delay = QuietHours::secondsUntilEnd($quietHours, $now);
        $delivery->scheduleRetry($delay);
        $delivery->save();
        throw new DelayedRequeueException($delay, 'Deferred for recipient quiet hours.');
      }
    }

    // The claim, terminal/availability guards, send, classification, status
    // roll-up and lifecycle events live in the shared sender so this worker and
    // the synchronous SendNow action cannot double-send or diverge. A retryable
    // failure leaves the row pending with next_attempt set; translate it into
    // the queue's backoff by delaying the SAME item.
    if ($this->deliverySender->send($delivery) === DeliveryOutcome::Retry) {
      $delay = max(0, (int) $delivery->get('next_attempt')->value - $now);
      throw new DelayedRequeueException($delay, 'Retry backoff.');
    }
  }

}
