<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for the OI Group entity type.
 */
interface OiGroupInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the group name.
   *
   * @return string
   *   The group name.
   */
  public function getName(): string;

  /**
   * Sets the group name.
   *
   * @param string $name
   *   The group name.
   *
   * @return $this
   */
  public function setName(string $name): self;

  /**
   * Gets the group description.
   *
   * @return string|null
   *   The group description or NULL if not set.
   */
  public function getDescription(): ?string;

  /**
   * Gets the parent group.
   *
   * @return \Drupal\openintranet_access\Entity\OiGroupInterface|null
   *   The parent group, or NULL if this is a root group.
   */
  public function getParent(): ?OiGroupInterface;

  /**
   * Sets the parent group.
   *
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface|null $parent
   *   The parent group, or NULL to make this a root group.
   *
   * @return $this
   */
  public function setParent(?OiGroupInterface $parent): self;

  /**
   * Gets the parent group ID.
   *
   * @return int|null
   *   The parent group ID, or NULL if this is a root group.
   */
  public function getParentId(): ?int;

  /**
   * Checks if the group is active.
   *
   * @return bool
   *   TRUE if the group is active.
   */
  public function isActive(): bool;

  /**
   * Sets the group active status.
   *
   * @param bool $active
   *   Whether the group is active.
   *
   * @return $this
   */
  public function setActive(bool $active): self;

  /**
   * Gets the full path of group names from root to this group.
   *
   * @return string[]
   *   Array of group names from root to this group.
   */
  public function getPath(): array;

  /**
   * Gets the depth of this group in the hierarchy.
   *
   * @return int
   *   The depth (0 for root groups).
   */
  public function getDepth(): int;

}
