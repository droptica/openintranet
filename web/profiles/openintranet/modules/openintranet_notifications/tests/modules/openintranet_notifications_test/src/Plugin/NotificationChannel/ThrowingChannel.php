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
 * Test channel that throws an Error while sending.
 */
#[NotificationChannel(
  id: 'throwing',
  label: new TranslatableMarkup('Throwing'),
  description: new TranslatableMarkup('Throws during send to exercise sender resilience.'),
)]
final class ThrowingChannel extends NotificationChannelBase {

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
    return 'throwing';
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationRecipient $recipient, NotificationMessage $message): DeliveryResult {
    throw new \Error('Unexpected channel failure.');
  }

}
