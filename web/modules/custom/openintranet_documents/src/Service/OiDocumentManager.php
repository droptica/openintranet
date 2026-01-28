<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\openintranet_documents\OiFolderInterface;

/**
 * Service for managing documents.
 */
final class OiDocumentManager implements OiDocumentManagerInterface {

  /**
   * Constructs the document manager.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getByFolder(?OiFolderInterface $folder = NULL, int $limit = 50, int $offset = 0): array {
    $storage = $this->entityTypeManager->getStorage('oi_document');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->sort('title')
      ->range($offset, $limit);

    if ($folder === NULL) {
      $query->notExists('folder');
    }
    else {
      $query->condition('folder', $folder->id());
    }

    $ids = $query->execute();
    return $ids ? $storage->loadMultiple($ids) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getRecent(int $limit = 10): array {
    $storage = $this->entityTypeManager->getStorage('oi_document');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->sort('changed', 'DESC')
      ->range(0, $limit)
      ->execute();

    return $ids ? $storage->loadMultiple($ids) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function search(string $query, int $limit = 20, int $offset = 0): array {
    $query = trim($query);
    if (empty($query)) {
      return ['results' => [], 'total' => 0];
    }

    $storage = $this->entityTypeManager->getStorage('oi_document');

    // Build count query.
    $countQuery = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1);

    $orGroup = $countQuery->orConditionGroup()
      ->condition('title', '%' . $query . '%', 'LIKE')
      ->condition('description', '%' . $query . '%', 'LIKE');
    $countQuery->condition($orGroup);

    $total = (int) $countQuery->count()->execute();

    // Build results query.
    $resultsQuery = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1);

    $orGroupResults = $resultsQuery->orConditionGroup()
      ->condition('title', '%' . $query . '%', 'LIKE')
      ->condition('description', '%' . $query . '%', 'LIKE');
    $resultsQuery->condition($orGroupResults);

    $ids = $resultsQuery
      ->sort('title')
      ->range($offset, $limit)
      ->execute();

    $results = $ids ? $storage->loadMultiple($ids) : [];

    return ['results' => $results, 'total' => $total];
  }

}
