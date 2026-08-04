<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Loads the forum_category tree and exposes related helpers.
 */
final class ForumCategoryTreeService implements ForumCategoryTreeServiceInterface {

  /**
   * Constructs a ForumCategoryTreeService.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function loadTree(): ForumCategoryTree {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $tree = $term_storage->loadTree('forum_category', 0, NULL, FALSE);
    $term_ids = array_map(static fn ($item): int => (int) $item->tid, $tree);
    $terms = $term_storage->loadMultiple($term_ids);
    $children_by_parent = [];
    $parent_by_id = [];

    foreach ($tree as $item) {
      $parent_id = (int) ($item->parents[0] ?? 0);
      $term_id = (int) $item->tid;
      $children_by_parent[$parent_id][] = $term_id;
      $parent_by_id[$term_id] = $parent_id;
    }

    return new ForumCategoryTree(
      tree: $tree,
      terms: $terms,
      childrenByParent: $children_by_parent,
      parentById: $parent_by_id,
      topLevelIds: $children_by_parent[0] ?? [],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildIcon(TermInterface $term): array|string {
    if (!$term->hasField('field_category_icon') || $term->get('field_category_icon')->isEmpty()) {
      return '';
    }

    return $this->entityTypeManager
      ->getViewBuilder('taxonomy_term')
      ->viewField($term->get('field_category_icon'), ['label' => 'hidden']);
  }

  /**
   * {@inheritdoc}
   */
  public function loadPostCountsPerTerm(array $termIds, ?int $sinceTimestamp = NULL): array {
    if ($termIds === []) {
      return [];
    }

    $query = $this->database->select('node__field_forum_category', 'fc');
    $query->addField('fc', 'field_forum_category_target_id', 'tid');
    $query->addExpression('COUNT(*)', 'cnt');
    $query->join('node_field_data', 'n', 'fc.entity_id = n.nid');
    $query->condition('n.type', 'forum_post');
    $query->condition('n.status', 1);
    $query->condition('fc.field_forum_category_target_id', $termIds, 'IN');
    if ($sinceTimestamp !== NULL) {
      $query->condition('n.created', $sinceTimestamp, '>=');
    }
    $query->groupBy('fc.field_forum_category_target_id');

    $counts = [];
    foreach ($query->execute()->fetchAll() as $row) {
      $counts[(int) $row->tid] = (int) $row->cnt;
    }
    return $counts;
  }

}
