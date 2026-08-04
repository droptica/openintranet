<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Exception;

/**
 * Exception thrown when a notification channel fails.
 */
class ChannelException extends \RuntimeException {

  /**
   * Constructs a ChannelException.
   *
   * @param string $channel
   *   The channel ID.
   * @param string $message
   *   The error message.
   * @param int $code
   *   The error code.
   * @param \Throwable|null $previous
   *   The previous exception.
   */
  public function __construct(
    public readonly string $channel,
    string $message,
    int $code = 0,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($message, $code, $previous);
  }

  /**
   * Creates an exception for unavailable channel.
   *
   * @param string $channel
   *   The channel ID.
   *
   * @return static
   */
  public static function unavailable(string $channel): static {
    return new static($channel, sprintf('Channel "%s" is not available or not configured.', $channel));
  }

  /**
   * Creates an exception for missing recipient address.
   *
   * @param string $channel
   *   The channel ID.
   * @param string $recipient_name
   *   The recipient name.
   *
   * @return static
   */
  public static function missingAddress(string $channel, string $recipient_name): static {
    return new static($channel, sprintf('Recipient "%s" has no address for channel "%s".', $recipient_name, $channel));
  }

  /**
   * Creates an exception for send failure.
   *
   * @param string $channel
   *   The channel ID.
   * @param string $reason
   *   The failure reason.
   * @param \Throwable|null $previous
   *   The previous exception.
   *
   * @return static
   */
  public static function sendFailed(string $channel, string $reason, ?\Throwable $previous = NULL): static {
    return new static($channel, sprintf('Failed to send via "%s": %s', $channel, $reason), 0, $previous);
  }

}
