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
 * Test channel that is never available.
 *
 * Lets the delivery-policy test exercise the availability filter: it can
 * address every recipient and would send successfully, yet isAvailable()
 * returns FALSE so the policy must drop it.
 */
#[NotificationChannel(
  id: 'unavailable_stub',
  label: new TranslatableMarkup('Unavailable stub'),
  description: new TranslatableMarkup('Always unavailable; exercises the policy availability filter.'),
)]
final class UnavailableStubChannel extends NotificationChannelBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientAddress(NotificationRecipient $recipient): ?string {
    return 'stub';
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationRecipient $recipient, NotificationMessage $message): DeliveryResult {
    return DeliveryResult::success();
  }

}
