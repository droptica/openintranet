<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining an OI Folder entity type.
 */
interface OiFolderInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the folder name.
   */
  public function getName(): string;

  /**
   * Sets the folder name.
   */
  public function setName(string $name): self;

  /**
   * Gets the folder description.
   */
  public function getDescription(): ?string;

  /**
   * Gets the parent folder.
   */
  public function getParent(): ?OiFolderInterface;

  /**
   * Sets the parent folder.
   */
  public function setParent(?OiFolderInterface $parent): self;

  /**
   * Gets the parent folder ID.
   */
  public function getParentId(): ?int;

  /**
   * Checks if folder is active.
   */
  public function isActive(): bool;

  /**
   * Sets the folder active status.
   */
  public function setActive(bool $active): self;

  /**
   * Gets the full path as array of folder names.
   *
   * @return string[]
   *   Array of folder names from root to current.
   */
  public function getPath(): array;

  /**
   * Gets the depth level (0 = root).
   */
  public function getDepth(): int;

}
