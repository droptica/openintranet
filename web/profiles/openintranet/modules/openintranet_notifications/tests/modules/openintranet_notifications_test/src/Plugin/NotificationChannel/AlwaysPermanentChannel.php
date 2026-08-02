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
 * Test channel that always reports a permanent failure.
 *
 * Drives the worker's no-retry path: a permanent failure must mark the delivery
 * failed and never re-enqueue.
 */
#[NotificationChannel(
  id: 'always_permanent',
  label: new TranslatableMarkup('Always permanent'),
  description: new TranslatableMarkup('Always returns a permanent failure; exercises the worker no-retry path.'),
)]
final class AlwaysPermanentChannel extends NotificationChannelBase {

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
    return 'permanent';
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationRecipient $recipient, NotificationMessage $message): DeliveryResult {
    return DeliveryResult::permanentFailure('E_PERM', 'nope');
  }

}
