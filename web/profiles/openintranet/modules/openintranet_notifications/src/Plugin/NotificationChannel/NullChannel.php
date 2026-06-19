<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationChannel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationChannel;
use Drupal\openintranet_notifications\Channel\NotificationChannelBase;
use Drupal\openintranet_notifications\Dto\DeliveryResult;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Discards every message; useful as a sink for tests and dry runs.
 */
#[NotificationChannel(
  id: 'null',
  label: new TranslatableMarkup('Null'),
  description: new TranslatableMarkup('Accepts every recipient and silently discards the message.'),
)]
final class NullChannel extends NotificationChannelBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientAddress(NotificationRecipient $recipient): ?string {
    // Synthetic non-null token so canSendTo() is always TRUE.
    return 'null';
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationRecipient $recipient, NotificationMessage $message): DeliveryResult {
    return DeliveryResult::success();
  }

}
