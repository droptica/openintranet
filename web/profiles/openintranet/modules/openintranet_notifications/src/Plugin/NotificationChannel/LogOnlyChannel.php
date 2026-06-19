<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationChannel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationChannel;
use Drupal\openintranet_notifications\Channel\NotificationChannelBase;
use Drupal\openintranet_notifications\Dto\DeliveryResult;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Logs the message subject instead of sending it; useful for diagnostics.
 */
#[NotificationChannel(
  id: 'log_only',
  label: new TranslatableMarkup('Log only'),
  description: new TranslatableMarkup('Accepts every recipient and logs the message subject instead of sending.'),
)]
final class LogOnlyChannel extends NotificationChannelBase {

  /**
   * Constructs a LogOnlyChannel.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   The module logger channel.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    private readonly LoggerInterface $logger,
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
      $container->get('logger.channel.openintranet_notifications'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientAddress(NotificationRecipient $recipient): ?string {
    // Synthetic non-null token so canSendTo() is always TRUE.
    return 'log';
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationRecipient $recipient, NotificationMessage $message): DeliveryResult {
    $this->logger->info('Notification (log_only): @subject', ['@subject' => $message->subject]);
    return DeliveryResult::success();
  }

}
