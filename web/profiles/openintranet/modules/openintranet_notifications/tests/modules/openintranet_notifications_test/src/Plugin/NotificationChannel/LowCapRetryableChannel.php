<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications_test\Plugin\NotificationChannel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationChannel;
use Drupal\openintranet_notifications\Channel\NotificationChannelBase;
use Drupal\openintranet_notifications\Dto\DeliveryResult;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test channel: always retryable, but with a per-channel cap of 2 attempts.
 *
 * Proves the per-channel max-attempts override (00-synteza §3.1): a transport
 * that lowers its retry budget exhausts and becomes permanent sooner than the
 * default 5, without the worker knowing the channel.
 */
#[NotificationChannel(
  id: 'low_cap_retryable',
  label: new TranslatableMarkup('Low-cap retryable'),
  description: new TranslatableMarkup('Always retryable with maxAttempts()=2; exercises the per-channel cap.'),
)]
final class LowCapRetryableChannel extends NotificationChannelBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function maxAttempts(): int {
    return 2;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientAddress(NotificationRecipient $recipient): ?string {
    return 'low_cap';
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationRecipient $recipient, NotificationMessage $message): DeliveryResult {
    return DeliveryResult::retryableFailure('E_RETRY', 'transient');
  }

}
