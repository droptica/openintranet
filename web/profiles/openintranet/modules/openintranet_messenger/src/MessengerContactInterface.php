<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a messenger contact entity type.
 */
interface MessengerContactInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the contact name.
   *
   * @return string
   *   The contact name.
   */
  public function getName(): string;

  /**
   * Sets the contact name.
   *
   * @param string $name
   *   The contact name.
   *
   * @return $this
   */
  public function setName(string $name): self;

  /**
   * Gets the contact email.
   *
   * @return string|null
   *   The contact email or NULL if not set.
   */
  public function getEmail(): ?string;

  /**
   * Sets the contact email.
   *
   * @param string $email
   *   The contact email.
   *
   * @return $this
   */
  public function setEmail(string $email): self;

  /**
   * Gets the contact phone number.
   *
   * @return string|null
   *   The phone number or NULL if not set.
   */
  public function getPhone(): ?string;

  /**
   * Sets the contact phone number.
   *
   * @param string $phone
   *   The phone number (international format).
   *
   * @return $this
   */
  public function setPhone(string $phone): self;

  /**
   * Gets the preferred notification channel.
   *
   * @return string
   *   The preferred channel (email, sms, both, etc.).
   */
  public function getPreferredChannel(): string;

  /**
   * Sets the preferred notification channel.
   *
   * @param string $channel
   *   The preferred channel.
   *
   * @return $this
   */
  public function setPreferredChannel(string $channel): self;

  /**
   * Checks if the contact is active.
   *
   * @return bool
   *   TRUE if active, FALSE otherwise.
   */
  public function isActive(): bool;

  /**
   * Sets the active status.
   *
   * @param bool $active
   *   The active status.
   *
   * @return $this
   */
  public function setActive(bool $active): self;

  /**
   * Gets the creation timestamp.
   *
   * @return int
   *   The creation timestamp.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the creation timestamp.
   *
   * @param int $timestamp
   *   The creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime(int $timestamp): self;

}
