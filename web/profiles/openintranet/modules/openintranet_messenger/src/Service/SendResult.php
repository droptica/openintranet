<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Service;

use Drupal\openintranet_messenger\Recipient\RecipientInterface;

/**
 * Value object representing a single send result.
 */
final class SendResult {

  /**
   * Constructs a SendResult object.
   *
   * @param \Drupal\openintranet_messenger\Recipient\RecipientInterface $recipient
   *   The recipient.
   * @param string $channel
   *   The channel used.
   * @param bool $success
   *   Whether the send was successful.
   * @param string|null $error
   *   The error message if failed.
   * @param string|null $address
   *   The address used.
   */
  public function __construct(
    public readonly RecipientInterface $recipient,
    public readonly string $channel,
    public readonly bool $success,
    public readonly ?string $error = NULL,
    public readonly ?string $address = NULL,
  ) {}

  /**
   * Creates a success result.
   *
   * @param \Drupal\openintranet_messenger\Recipient\RecipientInterface $recipient
   *   The recipient.
   * @param string $channel
   *   The channel used.
   * @param string $address
   *   The address used.
   *
   * @return self
   */
  public static function success(RecipientInterface $recipient, string $channel, string $address): self {
    return new self($recipient, $channel, TRUE, NULL, $address);
  }

  /**
   * Creates a failure result.
   *
   * @param \Drupal\openintranet_messenger\Recipient\RecipientInterface $recipient
   *   The recipient.
   * @param string $channel
   *   The channel attempted.
   * @param string $error
   *   The error message.
   * @param string|null $address
   *   The address attempted.
   *
   * @return self
   */
  public static function failure(RecipientInterface $recipient, string $channel, string $error, ?string $address = NULL): self {
    return new self($recipient, $channel, FALSE, $error, $address);
  }

  /**
   * Creates a skipped result.
   *
   * @param \Drupal\openintranet_messenger\Recipient\RecipientInterface $recipient
   *   The recipient.
   * @param string $channel
   *   The channel.
   * @param string $reason
   *   The skip reason.
   *
   * @return self
   */
  public static function skipped(RecipientInterface $recipient, string $channel, string $reason): self {
    return new self($recipient, $channel, FALSE, $reason, NULL);
  }

  /**
   * Checks if this result was skipped (no address).
   *
   * @return bool
   *   TRUE if skipped.
   */
  public function isSkipped(): bool {
    return !$this->success && $this->address === NULL;
  }

}
