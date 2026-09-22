<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a notification log entity type.
 */
interface NotificationLogInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Notification status: sent successfully.
   */
  public const STATUS_SENT = 'sent';

  /**
   * Notification status: failed to send.
   */
  public const STATUS_FAILED = 'failed';

  /**
   * Notification status: pending.
   */
  public const STATUS_PENDING = 'pending';

  /**
   * Gets the recipient type.
   *
   * @return string
   *   The recipient type ('user' or 'contact').
   */
  public function getRecipientType(): string;

  /**
   * Gets the recipient ID.
   *
   * @return int
   *   The recipient entity ID.
   */
  public function getRecipientId(): int;

  /**
   * Gets the recipient name.
   *
   * @return string
   *   The recipient name (denormalized).
   */
  public function getRecipientName(): string;

  /**
   * Gets the notification channel.
   *
   * @return string
   *   The channel (email, sms, etc.).
   */
  public function getChannel(): string;

  /**
   * Gets the recipient address.
   *
   * @return string
   *   The address (email, phone, etc.).
   */
  public function getRecipientAddress(): string;

  /**
   * Gets the notification status.
   *
   * @return string
   *   The status (sent, failed, pending).
   */
  public function getStatus(): string;

  /**
   * Sets the notification status.
   *
   * @param string $status
   *   The status.
   *
   * @return $this
   */
  public function setStatus(string $status): self;

  /**
   * Gets the error message if failed.
   *
   * @return string|null
   *   The error message or NULL.
   */
  public function getErrorMessage(): ?string;

  /**
   * Sets the error message.
   *
   * @param string $message
   *   The error message.
   *
   * @return $this
   */
  public function setErrorMessage(string $message): self;

  /**
   * Gets the sent timestamp.
   *
   * @return int
   *   The timestamp when the notification was sent.
   */
  public function getSentTime(): int;

}
