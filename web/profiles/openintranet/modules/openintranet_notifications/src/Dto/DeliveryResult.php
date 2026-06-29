<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Dto;

/**
 * Immutable result of a channel send attempt.
 *
 * Channels never throw on transport errors; they classify the outcome here so
 * the queue worker decides whether to retry (00-synteza §3.1).
 */
final class DeliveryResult {

  public function __construct(
    public readonly bool $success,
    public readonly bool $retryable,
    public readonly ?string $providerMessageId = NULL,
    public readonly ?string $errorCode = NULL,
    public readonly ?string $errorMessage = NULL,
  ) {}

  /**
   * Successful delivery, optionally carrying the provider message id.
   */
  public static function success(?string $providerMessageId = NULL): self {
    return new self(success: TRUE, retryable: FALSE, providerMessageId: $providerMessageId);
  }

  /**
   * Transient failure the worker should retry.
   */
  public static function retryableFailure(string $code, string $msg): self {
    return new self(success: FALSE, retryable: TRUE, errorCode: $code, errorMessage: $msg);
  }

  /**
   * Permanent failure the worker must not retry.
   */
  public static function permanentFailure(string $code, string $msg): self {
    return new self(success: FALSE, retryable: FALSE, errorCode: $code, errorMessage: $msg);
  }

}
