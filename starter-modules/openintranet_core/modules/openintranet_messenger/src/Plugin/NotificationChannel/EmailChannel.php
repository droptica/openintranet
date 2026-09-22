<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Plugin\NotificationChannel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_messenger\Channel\ChannelPluginBase;
use Drupal\openintranet_messenger\Channel\NotificationChannel;
use Drupal\openintranet_messenger\Exception\ChannelException;
use Drupal\openintranet_messenger\Recipient\RecipientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Email notification channel plugin.
 */
#[NotificationChannel(
  id: 'email',
  label: new TranslatableMarkup('Email'),
  description: new TranslatableMarkup('Send notifications via email using Drupal mail system.'),
  weight: 0,
)]
final class EmailChannel extends ChannelPluginBase {

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected MailManagerInterface $mailManager;

  /**
   * Constructs an EmailChannel object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerChannelInterface $logger,
    ConfigFactoryInterface $config_factory,
    MailManagerInterface $mail_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $config_factory);
    $this->mailManager = $mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.openintranet_messenger'),
      $container->get('config.factory'),
      $container->get('plugin.manager.mail'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    // Email is always available if the mail system is configured.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function send(RecipientInterface $recipient, string $subject, string $message): bool {
    $email = $this->getRecipientAddress($recipient);

    if (empty($email)) {
      throw ChannelException::missingAddress('email', $recipient->getName());
    }

    $this->logInfo('Messenger/Email: Sending to @email. Subject: @subject', [
      '@email' => $email,
      '@subject' => $subject,
    ]);

    $params = [
      'subject' => $subject,
      'body' => $message,
      'recipient_name' => $recipient->getName(),
    ];

    $result = $this->mailManager->mail(
      'openintranet_messenger',
      'notification',
      $email,
      $recipient->getLangcode(),
      $params,
      NULL,
      TRUE,
    );

    if (!$result['result']) {
      $this->logError('Messenger/Email: Failed to send to @email', [
        '@email' => $email,
      ]);
      throw ChannelException::sendFailed('email', 'Mail system returned failure');
    }

    $this->logInfo('Messenger/Email: Mail sent successfully to @email', [
      '@email' => $email,
    ]);

    return TRUE;
  }

}
