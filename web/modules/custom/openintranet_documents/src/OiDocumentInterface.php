<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\file\FileInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining an OI Document entity type.
 */
interface OiDocumentInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface, RevisionLogInterface {

  /**
   * Gets the document title.
   */
  public function getTitle(): string;

  /**
   * Sets the document title.
   */
  public function setTitle(string $title): self;

  /**
   * Gets the document description.
   */
  public function getDescription(): ?string;

  /**
   * Gets the folder.
   */
  public function getFolder(): ?OiFolderInterface;

  /**
   * Sets the folder.
   */
  public function setFolder(?OiFolderInterface $folder): self;

  /**
   * Gets the folder ID.
   */
  public function getFolderId(): ?int;

  /**
   * Gets the attached file.
   */
  public function getFile(): ?FileInterface;

  /**
   * Sets the attached file.
   */
  public function setFile(?FileInterface $file): self;


}
