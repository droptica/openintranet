<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Dto;

use Drupal\Core\Session\AccountInterface;

/**
 * Immutable recipient DTO carrying identity only.
 *
 * Each channel resolves the address it needs (00-synteza §8: the resolver
 * returns a DTO, not an email/phone; the channel decides whether it can handle
 * the recipient).
 */
final class NotificationRecipient {

  public function __construct(
    // Recipient kind: user|email|phone|endpoint|external.
    public readonly string $type,
    // User id when $type === 'user'.
    public readonly ?int $id = NULL,
    // Raw contact value for non-user types (email/phone/url/external id).
    public readonly ?string $value = NULL,
    public readonly string $langcode = 'en',
    // Loaded user; channels read mail/fields from it to avoid reloads.
    public readonly ?AccountInterface $account = NULL,
  ) {}

  /**
   * Whether this recipient is an internal user with an id.
   */
  public function isUser(): bool {
    return $this->type === 'user' && $this->id !== NULL;
  }

}
