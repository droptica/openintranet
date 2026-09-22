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
 * In-app inbox channel.
 *
 * The notification entity itself IS the inbox record, so delivery is a no-op:
 * persisting the notification already makes it visible in the recipient's
 * inbox. Only internal users can have an inbox.
 */
#[NotificationChannel(
  id: 'inbox',
  label: new TranslatableMarkup('In-app'),
  description: new TranslatableMarkup('Shows notifications in the Open Intranet notification center.'),
)]
final class InboxChannel extends NotificationChannelBase {

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
    // Only internal users have an inbox; the synthetic token makes canSendTo()
    // TRUE for users and NULL (FALSE) for every external recipient type.
    return $recipient->isUser() ? 'inbox' : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationRecipient $recipient, NotificationMessage $message): DeliveryResult {
    return DeliveryResult::success();
  }

}
