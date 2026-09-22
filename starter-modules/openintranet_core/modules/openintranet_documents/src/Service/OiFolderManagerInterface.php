<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Service;

use Drupal\openintranet_documents\OiFolderInterface;

/**
 * Interface for the folder manager service.
 */
interface OiFolderManagerInterface {

  /**
   * Gets child folders of a parent folder.
   *
   * @param \Drupal\openintranet_documents\OiFolderInterface|null $parent
   *   The parent folder, or NULL for root folders.
   *
   * @return \Drupal\openintranet_documents\OiFolderInterface[]
   *   Array of child folders.
   */
  public function getChildren(?OiFolderInterface $parent = NULL): array;

  /**
   * Gets the full path of folders from root to the given folder.
   *
   * @param \Drupal\openintranet_documents\OiFolderInterface $folder
   *   The folder.
   *
   * @return \Drupal\openintranet_documents\OiFolderInterface[]
   *   Array of folders from root to the given folder.
   */
  public function getPath(OiFolderInterface $folder): array;

  /**
   * Gets all ancestor folders of a folder.
   *
   * @param \Drupal\openintranet_documents\OiFolderInterface $folder
   *   The folder.
   *
   * @return \Drupal\openintranet_documents\OiFolderInterface[]
   *   Array of ancestor folders, ordered from root to parent.
   */
  public function getAncestors(OiFolderInterface $folder): array;

  /**
   * Checks if a folder has children.
   *
   * @param \Drupal\openintranet_documents\OiFolderInterface $folder
   *   The folder.
   *
   * @return bool
   *   TRUE if folder has children.
   */
  public function hasChildren(OiFolderInterface $folder): bool;

  /**
   * Counts documents in a folder.
   *
   * @param \Drupal\openintranet_documents\OiFolderInterface|null $folder
   *   The folder, or NULL for root level.
   * @param bool $recursive
   *   Whether to count documents in subfolders.
   *
   * @return int
   *   Number of documents.
   */
  public function countDocuments(?OiFolderInterface $folder = NULL, bool $recursive = FALSE): int;

  /**
   * Gets folder statistics (subfolder count and document count) for multiple folders.
   *
   * @param \Drupal\openintranet_documents\OiFolderInterface[] $folders
   *   Array of folders.
   *
   * @return array
   *   Associative array keyed by folder ID with values containing:
   *   - 'folders': int - number of subfolders
   *   - 'documents': int - number of documents
   */
  public function getFolderStats(array $folders): array;

  /**
   * Searches folders by name and description.
   *
   * @param string $query
   *   The search query.
   * @param int $limit
   *   Maximum number of results.
   * @param int $offset
   *   Offset for pagination.
   *
   * @return array
   *   Array with 'results' (OiFolderInterface[]) and 'total' (int).
   */
  public function search(string $query, int $limit = 20, int $offset = 0): array;

}
