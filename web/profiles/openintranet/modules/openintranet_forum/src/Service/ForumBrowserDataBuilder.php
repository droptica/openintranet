<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Default implementation of the forum browser data builder service.
 */
final class ForumBrowserDataBuilder implements ForumBrowserDataBuilderInterface {

  /**
   * Maximum posts shown per category group in the browser accordion.
   */
  private const MAX_POSTS_PER_GROUP = 3;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly ForumTrendingScorerInterface $trendingScorer,
    private readonly ForumCategoryTreeServiceInterface $categoryTreeService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildBrowserGroups(ForumCategoryTree $tree, array $filters): array {
    $nodes = $this->loadBrowserNodes($tree, $filters);
    $groups = $this->initializeGroups($tree);
    $groups = $this->bucketPostsIntoGroups($nodes, $groups, $tree);
    $prepared = $this->finalizeGroupDisplay($groups, $this->hasActiveFilters($filters));

    return ['groups' => $prepared];
  }

  /**
   * Buckets forum posts into their top-level and child category groups.
   *
   * @param \Drupal\node\NodeInterface[] $nodes
   *   Forum post nodes in display order.
   * @param array $groups
   *   Group map keyed by top-level category id, as produced by
   *   ::initializeGroups().
   * @param \Drupal\openintranet_forum\Service\ForumCategoryTree $tree
   *   Forum category tree DTO providing parent/child resolution.
   *
   * @return array
   *   The populated groups map, still keyed by top-level id.
   */
  private function bucketPostsIntoGroups(array $nodes, array $groups, ForumCategoryTree $tree): array {
    foreach ($nodes as $node) {
      if (!$node instanceof NodeInterface || !$node->hasField('field_forum_category') || $node->get('field_forum_category')->isEmpty()) {
        continue;
      }

      $category_id = (int) $node->get('field_forum_category')->target_id;
      [$top_level_id, $child_id] = $tree->resolveTopLevelAndChild($category_id);

      if (!isset($groups[$top_level_id])) {
        continue;
      }

      $groups[$top_level_id]['count']++;
      $post_item = $this->buildBrowserPostItem($node);

      if ($child_id !== NULL && isset($groups[$top_level_id]['children'][$child_id])) {
        $groups[$top_level_id]['children'][$child_id]['count']++;
        $this->appendCappedPost($groups[$top_level_id]['children'][$child_id]['posts'], $post_item);
      }
      else {
        $this->appendCappedPost($groups[$top_level_id]['posts'], $post_item);
      }
    }

    return $groups;
  }

  /**
   * Filters empty groups/children and assigns is_open flags for display.
   */
  private function finalizeGroupDisplay(array $groups, bool $hasActiveFilters): array {
    $prepared_groups = [];
    $is_first_group = TRUE;

    foreach ($groups as $group) {
      $children = array_values(array_filter(
        $group['children'],
        static fn (array $child): bool => $child['count'] > 0 || $child['posts'] !== [],
      ));

      if ($group['count'] === 0 && $group['posts'] === [] && $children === []) {
        continue;
      }

      $is_first_child = TRUE;
      foreach ($children as &$child) {
        $child['is_open'] = $hasActiveFilters || $is_first_child;
        $is_first_child = FALSE;
      }
      unset($child);

      $group['children'] = $children;
      $group['is_open'] = $hasActiveFilters || $is_first_group;
      $prepared_groups[] = $group;
      $is_first_group = FALSE;
    }

    return $prepared_groups;
  }

  /**
   * Creates empty top-level and direct child groups from the tree.
   */
  private function initializeGroups(ForumCategoryTree $tree): array {
    $groups = [];

    foreach ($tree->topLevelIds as $top_level_id) {
      $top_term = $tree->terms[$top_level_id] ?? NULL;
      if (!$top_term instanceof TermInterface) {
        continue;
      }

      $groups[$top_level_id] = $this->buildGroupStructure($top_term, $top_level_id, TRUE);

      foreach ($tree->childrenByParent[$top_level_id] ?? [] as $child_id) {
        $child_term = $tree->terms[$child_id] ?? NULL;
        if (!$child_term instanceof TermInterface) {
          continue;
        }

        $groups[$top_level_id]['children'][$child_id] = $this->buildGroupStructure($child_term, $child_id, FALSE);
      }
    }

    return $groups;
  }

  /**
   * Builds an empty group structure for a category term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The category term.
   * @param int $termId
   *   The term id.
   * @param bool $withChildren
   *   TRUE to include an empty children map (top-level groups only).
   *
   * @return array
   *   The initialized group array.
   */
  private function buildGroupStructure(TermInterface $term, int $termId, bool $withChildren): array {
    $group = [
      'term_id' => $termId,
      'title' => $term->label(),
      'icon' => $this->categoryTreeService->buildIcon($term),
      'count' => 0,
      'is_open' => FALSE,
      'posts' => [],
    ];

    if ($withChildren) {
      $group['children'] = [];
    }

    return $group;
  }

  /**
   * Appends a post item to a group's list while below the per-group cap.
   *
   * @param array $posts
   *   The group's post list, by reference.
   * @param array $item
   *   The post item to append.
   */
  private function appendCappedPost(array &$posts, array $item): void {
    if (count($posts) < self::MAX_POSTS_PER_GROUP) {
      $posts[] = $item;
    }
  }

  /**
   * Loads browser nodes according to the selected filters.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Matching forum posts in display order.
   */
  private function loadBrowserNodes(ForumCategoryTree $tree, array $filters): array {
    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'forum_post')
      ->condition('status', 1);

    if ($filters['q'] !== '') {
      $search = $query->orConditionGroup()
        ->condition('title', $filters['q'], 'CONTAINS')
        ->condition('body.value', $filters['q'], 'CONTAINS');
      $query->condition($search);
    }

    if ($filters['tag'] > 0) {
      $query->condition('field_forum_tags.target_id', $filters['tag']);
    }

    if ($filters['author'] > 0) {
      $query->condition('uid', $filters['author']);
    }

    if ($filters['category'] > 0) {
      $query->condition(
        'field_forum_category.target_id',
        $tree->descendantsOf($filters['category'], TRUE),
        'IN',
      );
    }

    if ($filters['sort'] === 'popular') {
      $thirty_days_ago = $this->time->getRequestTime() - 30 * 86400;
      $query->condition('created', $thirty_days_ago, '>=');
    }

    switch ($filters['sort']) {
      case 'popular':
        break;

      case 'active':
        $query->sort('field_forum_reply_count.value', 'DESC')
          ->sort('created', 'DESC');
        break;

      default:
        $query->sort('sticky', 'DESC')
          ->sort('created', 'DESC');
    }

    $query->range(0, 500);

    $node_ids = $query->execute();
    if ($node_ids === []) {
      return [];
    }

    if ($filters['sort'] === 'popular') {
      $node_ids = $this->trendingScorer->sortNodeIdsByScore($node_ids);
      if ($node_ids === []) {
        return [];
      }
    }

    return $this->entityTypeManager->getStorage('node')->loadMultiple($node_ids);
  }

  /**
   * Builds the render array for a single post row in the browser layout.
   */
  private function buildBrowserPostItem(NodeInterface $node): array {
    return $this->entityTypeManager
      ->getViewBuilder('node')
      ->view($node, 'category_browser_row');
  }

  /**
   * Checks whether any filter other than default sort is active.
   */
  private function hasActiveFilters(array $filters): bool {
    return $filters['q'] !== ''
      || $filters['category'] > 0
      || $filters['tag'] > 0
      || $filters['author'] > 0
      || $filters['sort'] !== 'recent';
  }

}
