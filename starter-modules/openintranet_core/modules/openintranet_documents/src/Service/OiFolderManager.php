<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\openintranet_documents\OiFolderInterface;

/**
 * Service for managing folders.
 */
final class OiFolderManager implements OiFolderManagerInterface {

  /**
   * Constructs the folder manager.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getChildren(?OiFolderInterface $parent = NULL): array {
    $storage = $this->entityTypeManager->getStorage('oi_folder');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->sort('name');

    if ($parent === NULL) {
      $query->condition('parent', NULL, 'IS NULL');
    }
    else {
      $query->condition('parent', $parent->id());
    }

    $ids = $query->execute();
    return $ids ? $storage->loadMultiple($ids) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPath(OiFolderInterface $folder): array {
    $path = [$folder];
    $ancestors = $this->getAncestors($folder);
    return array_merge($ancestors, $path);
  }

  /**
   * {@inheritdoc}
   */
  public function getAncestors(OiFolderInterface $folder): array {
    $ancestors = [];
    $parent = $folder->getParent();
    $maxDepth = 50;

    while ($parent !== NULL && $maxDepth-- > 0) {
      array_unshift($ancestors, $parent);
      $parent = $parent->getParent();
    }

    return $ancestors;
  }

  /**
   * {@inheritdoc}
   */
  public function hasChildren(OiFolderInterface $folder): bool {
    $storage = $this->entityTypeManager->getStorage('oi_folder');
    $count = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('parent', $folder->id())
      ->count()
      ->execute();

    return $count > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function countDocuments(?OiFolderInterface $folder = NULL, bool $recursive = FALSE): int {
    $storage = $this->entityTypeManager->getStorage('oi_document');

    if (!$recursive) {
      $query = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('status', 1);

      if ($folder === NULL) {
        $query->notExists('folder');
      }
      else {
        $query->condition('folder', $folder->id());
      }

      return (int) $query->count()->execute();
    }

    // Recursive count - include all subfolders.
    $folderIds = $folder ? $this->getAllDescendantIds($folder) : [];
    if ($folder) {
      $folderIds[] = $folder->id();
    }

    if (empty($folderIds) && $folder !== NULL) {
      return 0;
    }

    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1);

    if ($folder === NULL) {
      // Count all documents.
    }
    else {
      $query->condition('folder', $folderIds, 'IN');
    }

    return (int) $query->count()->execute();
  }

  /**
   * Gets all descendant folder IDs.
   *
   * @param \Drupal\openintranet_documents\OiFolderInterface $folder
   *   The parent folder.
   *
   * @return int[]
   *   Array of descendant folder IDs.
   */
  private function getAllDescendantIds(OiFolderInterface $folder): array {
    $ids = [];
    $children = $this->getChildren($folder);

    foreach ($children as $child) {
      $ids[] = (int) $child->id();
      $ids = array_merge($ids, $this->getAllDescendantIds($child));
    }

    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getFolderStats(array $folders): array {
    if (empty($folders)) {
      return [];
    }

    $folderIds = array_map(fn($folder) => (int) $folder->id(), $folders);
    $stats = array_fill_keys($folderIds, ['folders' => 0, 'documents' => 0]);

    // Count subfolders for each folder using a single query.
    $subfolderCounts = $this->database->select('oi_folder', 'f')
      ->fields('f', ['parent'])
      ->condition('f.parent', $folderIds, 'IN')
      ->condition('f.status', 1)
      ->groupBy('f.parent')
      ->execute();

    // Add count expression.
    $query = $this->database->select('oi_folder', 'f');
    $query->addField('f', 'parent');
    $query->addExpression('COUNT(*)', 'cnt');
    $query->condition('f.parent', $folderIds, 'IN');
    $query->condition('f.status', 1);
    $query->groupBy('f.parent');

    foreach ($query->execute() as $row) {
      $parentId = (int) $row->parent;
      if (isset($stats[$parentId])) {
        $stats[$parentId]['folders'] = (int) $row->cnt;
      }
    }

    // Count documents for each folder using a single query.
    $docQuery = $this->database->select('oi_document', 'd');
    $docQuery->addField('d', 'folder');
    $docQuery->addExpression('COUNT(*)', 'cnt');
    $docQuery->condition('d.folder', $folderIds, 'IN');
    $docQuery->condition('d.status', 1);
    $docQuery->groupBy('d.folder');

    foreach ($docQuery->execute() as $row) {
      $folderId = (int) $row->folder;
      if (isset($stats[$folderId])) {
        $stats[$folderId]['documents'] = (int) $row->cnt;
      }
    }

    return $stats;
  }

  /**
   * {@inheritdoc}
   */
  public function search(string $query, int $limit = 20, int $offset = 0): array {
    $query = trim($query);
    if (empty($query)) {
      return ['results' => [], 'total' => 0];
    }

    $storage = $this->entityTypeManager->getStorage('oi_folder');

    // Build count query.
    $countQuery = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1);

    $orGroup = $countQuery->orConditionGroup()
      ->condition('name', '%' . $query . '%', 'LIKE')
      ->condition('description', '%' . $query . '%', 'LIKE');
    $countQuery->condition($orGroup);

    $total = (int) $countQuery->count()->execute();

    // Build results query.
    $resultsQuery = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1);

    $orGroupResults = $resultsQuery->orConditionGroup()
      ->condition('name', '%' . $query . '%', 'LIKE')
      ->condition('description', '%' . $query . '%', 'LIKE');
    $resultsQuery->condition($orGroupResults);

    $ids = $resultsQuery
      ->sort('name')
      ->range($offset, $limit)
      ->execute();

    $results = $ids ? $storage->loadMultiple($ids) : [];

    return ['results' => $results, 'total' => $total];
  }

}
