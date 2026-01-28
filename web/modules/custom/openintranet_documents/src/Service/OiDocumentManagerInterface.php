<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Service;

use Drupal\openintranet_documents\OiFolderInterface;

/**
 * Interface for the document manager service.
 */
interface OiDocumentManagerInterface {

  /**
   * Gets documents in a folder.
   *
   * @param \Drupal\openintranet_documents\OiFolderInterface|null $folder
   *   The folder, or NULL for root level documents.
   * @param int $limit
   *   Maximum number of documents to return.
   * @param int $offset
   *   Offset for pagination.
   *
   * @return \Drupal\openintranet_documents\OiDocumentInterface[]
   *   Array of documents.
   */
  public function getByFolder(?OiFolderInterface $folder = NULL, int $limit = 50, int $offset = 0): array;

  /**
   * Gets recent documents.
   *
   * @param int $limit
   *   Maximum number of documents to return.
   *
   * @return \Drupal\openintranet_documents\OiDocumentInterface[]
   *   Array of recent documents.
   */
  public function getRecent(int $limit = 10): array;

  /**
   * Searches documents by title, description, and filename.
   *
   * @param string $query
   *   Search query.
   * @param int $limit
   *   Maximum number of documents to return.
   * @param int $offset
   *   Offset for pagination.
   *
   * @return array
   *   Array with 'results' (OiDocumentInterface[]) and 'total' (int).
   */
  public function search(string $query, int $limit = 20, int $offset = 0): array;

}
