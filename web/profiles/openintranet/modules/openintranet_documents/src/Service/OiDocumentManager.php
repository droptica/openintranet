<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Service;

use Drupal\Core\Database\Connection;
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
    private readonly Connection $database,
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
  public function search(string $searchQuery, int $limit = 20, int $offset = 0): array {
    $searchQuery = trim($searchQuery);
    if (empty($searchQuery)) {
      return ['results' => [], 'total' => 0];
    }

    $storage = $this->entityTypeManager->getStorage('oi_document');
    $searchPattern = '%' . $searchQuery . '%';

    // Use database query to search including filename.
    // Join oi_document with file_managed via the file field.
    $query = $this->database->select('oi_document', 'd');
    $query->leftJoin('file_managed', 'f', 'd.file__target_id = f.fid');
    $query->fields('d', ['id']);
    $query->condition('d.status', 1);

    // Search in title, description, or filename.
    $orGroup = $query->orConditionGroup()
      ->condition('d.title', $searchPattern, 'LIKE')
      ->condition('d.description', $searchPattern, 'LIKE')
      ->condition('f.filename', $searchPattern, 'LIKE');
    $query->condition($orGroup);

    // Get total count.
    $countQuery = clone $query;
    $total = (int) $countQuery->countQuery()->execute()->fetchField();

    // Get paginated results.
    $query->orderBy('d.title');
    $query->range($offset, $limit);
    $ids = $query->execute()->fetchCol();

    $results = $ids ? $storage->loadMultiple($ids) : [];

    return ['results' => $results, 'total' => $total];
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencingContent(int $documentId): array {
    $results = [];

    // Find all entity_reference fields that target oi_document.
    // We need to load all entity_reference fields and filter manually
    // since loadByProperties doesn't work with nested settings.
    $fieldStorages = $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->loadByProperties([
        'type' => 'entity_reference',
      ]);

    if (empty($fieldStorages)) {
      return [];
    }

    // Filter to only fields targeting oi_document.
    $documentFields = [];
    foreach ($fieldStorages as $fieldStorage) {
      if ($fieldStorage->getSetting('target_type') === 'oi_document') {
        $documentFields[] = $fieldStorage;
      }
    }

    if (empty($documentFields)) {
      return [];
    }

    // For each field, query entities that reference this document.
    foreach ($documentFields as $fieldStorage) {
      $entityType = $fieldStorage->getTargetEntityTypeId();
      $fieldName = $fieldStorage->getName();

      // Only handle node entities for now.
      if ($entityType !== 'node') {
        continue;
      }

      try {
        $storage = $this->entityTypeManager->getStorage($entityType);
        $ids = $storage->getQuery()
          ->accessCheck(TRUE)
          ->condition('status', 1)
          ->condition($fieldName, $documentId)
          ->sort('title')
          ->execute();

        if ($ids) {
          $entities = $storage->loadMultiple($ids);
          foreach ($entities as $entity) {
            // Use entity ID as key to avoid duplicates.
            $results[$entityType . ':' . $entity->id()] = $entity;
          }
        }
      }
      catch (\Exception $e) {
        // Skip if query fails (e.g., field not on this bundle).
        continue;
      }
    }

    return array_values($results);
  }

}
