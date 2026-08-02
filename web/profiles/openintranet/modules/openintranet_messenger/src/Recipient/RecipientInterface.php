<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Recipient;

/**
 * Interface for notification recipients.
 *
 * Provides a unified interface for both Drupal users and external contacts.
 */
interface RecipientInterface {

  /**
   * Gets the recipient display name.
   *
   * @return string
   *   The display name.
   */
  public function getName(): string;

  /**
   * Gets the recipient email address.
   *
   * @return string|null
   *   The email address or NULL if not available.
   */
  public function getEmail(): ?string;

  /**
   * Gets the recipient phone number.
   *
   * @return string|null
   *   The phone number or NULL if not available.
   */
  public function getPhone(): ?string;

  /**
   * Gets the recipient's preferred notification channel.
   *
   * @return string
   *   The preferred channel (email, sms, both, etc.).
   */
  public function getPreferredChannel(): string;

  /**
   * Gets the address for a specific channel.
   *
   * @param string $channel_id
   *   The channel plugin ID (email, sms, slack, etc.).
   *
   * @return string|null
   *   The channel-specific address or NULL if not available.
   */
  public function getChannelAddress(string $channel_id): ?string;

  /**
   * Gets the recipient language code.
   *
   * @return string
   *   The language code.
   */
  public function getLangcode(): string;

  /**
   * Gets the source entity information.
   *
   * @return array
   *   Associative array with:
   *   - type: 'user' or 'contact'
   *   - id: The entity ID
   */
  public function getSourceInfo(): array;

  /**
   * Checks if the recipient can receive notifications via a specific channel.
   *
   * @param string $channel_id
   *   The channel plugin ID.
   *
   * @return bool
   *   TRUE if the recipient has a valid address for the channel.
   */
  public function canReceiveVia(string $channel_id): bool;

}
