<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Dto;

/**
 * Immutable rendered message DTO.
 *
 * Holds the channel-agnostic rendered content (00-synteza §8). The notification
 * is rendered once and stored; channels read this DTO at send time.
 */
final class NotificationMessage {

  public function __construct(
    public readonly string $subject,
    public readonly string $body,
    public readonly string $summary = '',
    public readonly array $payload = [],
  ) {}

  /**
   * Returns the summary, or the body stripped of tags and truncated.
   *
   * @param int $len
   *   Maximum length of the truncated body fallback.
   *
   * @return string
   *   The summary when set, otherwise the truncated plain-text body.
   */
  public function getSummaryOrTruncatedBody(int $len): string {
    if ($this->summary !== '') {
      return $this->summary;
    }
    return mb_substr(strip_tags($this->body), 0, $len);
  }

}
