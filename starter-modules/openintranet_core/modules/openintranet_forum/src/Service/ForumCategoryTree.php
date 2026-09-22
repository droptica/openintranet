<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

/**
 * Immutable snapshot of the forum_category taxonomy tree.
 *
 * Holds the raw loadTree() result plus pre-computed parent/child maps,
 * and exposes walk helpers so callers never touch the raw arrays.
 */
final readonly class ForumCategoryTree {

  /**
   * Constructs a ForumCategoryTree value object.
   *
   * @param array $tree
   *   Raw objects from TermStorageInterface::loadTree() (used for hierarchical
   *   select lists that need depth).
   * @param array<int, \Drupal\taxonomy\TermInterface> $terms
   *   Keyed by term id.
   * @param array<int, array<int, int>> $childrenByParent
   *   Parent id => list of child term ids. Key 0 = top-level.
   * @param array<int, int> $parentById
   *   Child id => parent id (0 if top-level).
   * @param array<int, int> $topLevelIds
   *   Ids of terms whose parent is 0.
   */
  public function __construct(
    public array $tree,
    public array $terms,
    public array $childrenByParent,
    public array $parentById,
    public array $topLevelIds,
  ) {}

  /**
   * Returns the top-level ancestor id of $categoryId, or 0 if not found / 0.
   */
  public function resolveTopLevel(int $categoryId): int {
    if ($categoryId === 0) {
      return 0;
    }

    $current = $categoryId;
    while (($this->parentById[$current] ?? 0) !== 0) {
      $current = $this->parentById[$current];
    }

    return $current;
  }

  /**
   * Resolves the top-level id and the child under it for $categoryId.
   *
   * Returns [topLevelId, immediateChildOfTopLevelId|null] for $categoryId.
   * The child is the ancestor that sits directly under the top-level. For a
   * top-level id this returns [id, null]. For an unknown id this returns
   * [0, null].
   *
   * @return array{0: int, 1: int|null}
   *   The top-level id and the immediate child id under it (NULL when none).
   */
  public function resolveTopLevelAndChild(int $categoryId): array {
    if ($categoryId === 0 || !isset($this->parentById[$categoryId])) {
      return [0, NULL];
    }

    if ($this->parentById[$categoryId] === 0) {
      return [$categoryId, NULL];
    }

    $path = [];
    $current = $categoryId;
    while (($this->parentById[$current] ?? 0) !== 0) {
      $path[] = $current;
      $current = $this->parentById[$current];
    }

    $reversed = array_reverse($path);

    return [$current, $reversed[0] ?? NULL];
  }

  /**
   * Sums a per-term count for a top-level term plus its direct children.
   *
   * @param int $topLevelId
   *   The top-level term id.
   * @param array<int, int> $countsByTerm
   *   Post counts keyed by term id.
   *
   * @return int
   *   The combined count for the term and its immediate children.
   */
  public function aggregateCount(int $topLevelId, array $countsByTerm): int {
    $total = (int) ($countsByTerm[$topLevelId] ?? 0);
    foreach ($this->childrenByParent[$topLevelId] ?? [] as $child_id) {
      $total += (int) ($countsByTerm[$child_id] ?? 0);
    }

    return $total;
  }

  /**
   * Returns all descendant term ids of $categoryId, optionally including it.
   *
   * @return list<int>
   *   The descendant term ids.
   */
  public function descendantsOf(int $categoryId, bool $includeSelf = FALSE): array {
    $collected = [];
    if ($includeSelf) {
      $collected[] = $categoryId;
    }

    $stack = [$categoryId];
    while ($stack !== []) {
      $current = array_pop($stack);
      foreach ($this->childrenByParent[$current] ?? [] as $child_id) {
        $collected[] = $child_id;
        $stack[] = $child_id;
      }
    }

    return array_values(array_unique($collected));
  }

}
