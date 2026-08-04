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
   * Builds a user recipient from a loaded account.
   *
   * The single seam for the "load user → user recipient" build duplicated
   * across the dispatcher, sender, actions and resolver base. Langcode always
   * comes from the account's preferred langcode so the channels render in the
   * recipient's language.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The recipient account.
   *
   * @return self
   *   The user recipient DTO.
   */
  public static function forUser(AccountInterface $user): self {
    return new self(
      type: 'user',
      id: (int) $user->id(),
      langcode: $user->getPreferredLangcode(),
      account: $user,
    );
  }

  /**
   * Builds a user recipient from a uid, tolerating a missing account.
   *
   * Used where the recipient may have been deleted since the row was written:
   * the langcode falls back to English when no account is available.
   *
   * @param int $uid
   *   The recipient user id.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The loaded account, or NULL when it could not be loaded.
   *
   * @return self
   *   The user recipient DTO.
   */
  public static function forUserId(int $uid, ?AccountInterface $account = NULL): self {
    return new self(
      type: 'user',
      id: $uid,
      langcode: $account !== NULL ? $account->getPreferredLangcode() : 'en',
      account: $account,
    );
  }

  /**
   * Whether this recipient is an internal user with an id.
   */
  public function isUser(): bool {
    return $this->type === 'user' && $this->id !== NULL;
  }

}
