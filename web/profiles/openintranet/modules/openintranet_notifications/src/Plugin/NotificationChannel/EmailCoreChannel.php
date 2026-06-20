<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationChannel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationChannel;
use Drupal\openintranet_notifications\Channel\NotificationChannelBase;
use Drupal\openintranet_notifications\Dto\DeliveryResult;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sends notifications via Drupal core mail (plugin.manager.mail).
 *
 * Addresses internal users by their account mail and email-type recipients by
 * their raw value. The companion hook_mail() (key 'notification') populates the
 * subject and body before the configured mail backend sends it.
 */
#[NotificationChannel(
  id: 'email_core',
  label: new TranslatableMarkup('Email (core)'),
  description: new TranslatableMarkup('Sends the message as an email via Drupal core mail.'),
)]
final class EmailCoreChannel extends NotificationChannelBase {

  /**
   * Constructs an EmailCoreChannel.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The core mail plugin manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    private readonly MailManagerInterface $mailManager,
    private readonly LanguageManagerInterface $languageManager,
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
      $container->get('plugin.manager.mail'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientAddress(NotificationRecipient $recipient): ?string {
    return $this->resolveUserOrEmailAddress($recipient);
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationRecipient $recipient, NotificationMessage $message): DeliveryResult {
    $address = $this->getRecipientAddress($recipient);
    if ($address === NULL) {
      return DeliveryResult::permanentFailure('NO_ADDRESS', 'No email address for recipient.');
    }

    $langcode = $recipient->langcode ?: LanguageInterface::LANGCODE_DEFAULT;

    try {
      $result = $this->mailManager->mail(
        'openintranet_notifications',
        'notification',
        $address,
        $langcode,
        [
          'subject' => $message->subject,
          'body' => $message->body,
        ],
      );
    }
    catch (\Throwable $e) {
      return DeliveryResult::retryableFailure('MAIL_EXCEPTION', $e->getMessage());
    }

    if (!empty($result['result'])) {
      return DeliveryResult::success();
    }
    return DeliveryResult::retryableFailure('MAIL_FAILED', 'Core mail returned failure.');
  }

}
