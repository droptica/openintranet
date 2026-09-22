<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Channel;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;

/**
 * Base class for notification channel plugins.
 *
 * Concrete channels implement ContainerFactoryPluginInterface::create() to
 * receive their own service dependencies.
 */
abstract class NotificationChannelBase extends PluginBase implements NotificationChannelInterface, ContainerFactoryPluginInterface {

  /**
   * The default per-channel retry budget (00-synteza §3.1).
   */
  protected const DEFAULT_MAX_ATTEMPTS = 5;

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) ($this->getPluginDefinition()['label'] ?? $this->getPluginId());
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    // Channel-config availability only; the global kill switch is enforced by
    // the delivery policy. Real channels override this with their own check.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function maxAttempts(): int {
    return static::DEFAULT_MAX_ATTEMPTS;
  }

  /**
   * {@inheritdoc}
   */
  public function canSendTo(NotificationRecipient $recipient): bool {
    return $this->getRecipientAddress($recipient) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isEscalationTier(): bool {
    return FALSE;
  }

  /**
   * Resolves an email address for a user or email-type recipient.
   *
   * Shared by the email channels: a user is addressed by its account mail and
   * an email-type recipient by its raw value; everything else is unaddressable.
   *
   * @param \Drupal\openintranet_notifications\Dto\NotificationRecipient $recipient
   *   The recipient to address.
   *
   * @return string|null
   *   The email address, or NULL when the recipient has none.
   */
  protected function resolveUserOrEmailAddress(NotificationRecipient $recipient): ?string {
    if ($recipient->isUser() && $recipient->account !== NULL) {
      $mail = $recipient->account->getEmail();
      if ($mail !== NULL && $mail !== '') {
        return $mail;
      }
    }
    if ($recipient->type === 'email' && $recipient->value !== NULL && $recipient->value !== '') {
      return $recipient->value;
    }
    return NULL;
  }

}
