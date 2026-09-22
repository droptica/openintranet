<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_forum\Unit\Service;

use Drupal\openintranet_forum\Service\ForumCategoryTree;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ForumCategoryTree DTO walk helpers.
 *
 * @covers \Drupal\openintranet_forum\Service\ForumCategoryTree
 */
#[CoversClass(ForumCategoryTree::class)]
final class ForumCategoryTreeTest extends TestCase {

  /**
   * Builds a fixture tree used across the tests.
   *
   * Tree shape:
   *        (0)
   *       / | \
   *      1  2  3      <- top-level
   *     / \     \
   *    4   5     6    <- children of 1 and 3
   *    |
   *    7              <- grandchild under 4
   */
  private function buildTree(): ForumCategoryTree {
    $childrenByParent = [
      0 => [1, 2, 3],
      1 => [4, 5],
      3 => [6],
      4 => [7],
    ];
    $parentById = [
      1 => 0,
      2 => 0,
      3 => 0,
      4 => 1,
      5 => 1,
      6 => 3,
      7 => 4,
    ];
    $topLevelIds = [1, 2, 3];

    return new ForumCategoryTree(
      tree: [],
      terms: [],
      childrenByParent: $childrenByParent,
      parentById: $parentById,
      topLevelIds: $topLevelIds,
    );
  }

  /**
   * Resolves the top-level id of the root request to 0.
   */
  #[Test]
  public function resolveTopLevelReturnsZeroForZero(): void {
    $tree = $this->buildTree();
    self::assertSame(0, $tree->resolveTopLevel(0));
  }

  /**
   * Resolves a top-level id to itself.
   */
  #[Test]
  public function resolveTopLevelReturnsSelfForTopLevel(): void {
    $tree = $this->buildTree();
    self::assertSame(1, $tree->resolveTopLevel(1));
  }

  /**
   * Resolves a direct child to its top-level parent.
   */
  #[Test]
  public function resolveTopLevelReturnsParentForDirectChild(): void {
    $tree = $this->buildTree();
    self::assertSame(1, $tree->resolveTopLevel(4));
  }

  /**
   * Walks up from a grandchild to its top-level ancestor.
   */
  #[Test]
  public function resolveTopLevelWalksUpFromGrandchild(): void {
    $tree = $this->buildTree();
    self::assertSame(1, $tree->resolveTopLevel(7));
  }

  /**
   * Resolves the top-level ancestor of a child on another branch.
   */
  #[Test]
  public function resolveTopLevelFindsAncestorAcrossOtherBranch(): void {
    $tree = $this->buildTree();
    self::assertSame(3, $tree->resolveTopLevel(6));
  }

  /**
   * Resolves an unknown id to itself.
   */
  #[Test]
  public function resolveTopLevelReturnsSelfForUnknownId(): void {
    $tree = $this->buildTree();
    self::assertSame(99, $tree->resolveTopLevel(99));
  }

  /**
   * Resolves the root request to a zero top-level and null child.
   */
  #[Test]
  public function resolveTopLevelAndChildReturnsZeroNullForZero(): void {
    $tree = $this->buildTree();
    self::assertSame([0, NULL], $tree->resolveTopLevelAndChild(0));
  }

  /**
   * Resolves an unknown id to a zero top-level and null child.
   */
  #[Test]
  public function resolveTopLevelAndChildReturnsZeroNullForUnknown(): void {
    $tree = $this->buildTree();
    self::assertSame([0, NULL], $tree->resolveTopLevelAndChild(99));
  }

  /**
   * Resolves a top-level id to itself with a null child.
   */
  #[Test]
  public function resolveTopLevelAndChildReturnsSelfNullForTopLevel(): void {
    $tree = $this->buildTree();
    self::assertSame([1, NULL], $tree->resolveTopLevelAndChild(1));
  }

  /**
   * Resolves a direct child to its top-level parent and itself.
   */
  #[Test]
  public function resolveTopLevelAndChildReturnsPairForDirectChild(): void {
    $tree = $this->buildTree();
    self::assertSame([1, 4], $tree->resolveTopLevelAndChild(4));
  }

  /**
   * Resolves a grandchild to its top-level and immediate child branch.
   */
  #[Test]
  public function resolveTopLevelAndChildReturnsImmediateChildForGrandchild(): void {
    $tree = $this->buildTree();
    self::assertSame([1, 4], $tree->resolveTopLevelAndChild(7));
  }

  /**
   * Resolves a child on another branch to its top-level and itself.
   */
  #[Test]
  public function resolveTopLevelAndChildCrossesOtherBranch(): void {
    $tree = $this->buildTree();
    self::assertSame([3, 6], $tree->resolveTopLevelAndChild(6));
  }

  /**
   * Lists all descendants of a top-level id excluding itself.
   */
  #[Test]
  public function descendantsOfTopLevelWithoutSelf(): void {
    $tree = $this->buildTree();
    self::assertEqualsCanonicalizing([4, 5, 7], $tree->descendantsOf(1));
  }

  /**
   * Lists all descendants of a top-level id including itself.
   */
  #[Test]
  public function descendantsOfTopLevelWithSelf(): void {
    $tree = $this->buildTree();
    self::assertEqualsCanonicalizing([1, 4, 5, 7], $tree->descendantsOf(1, TRUE));
  }

  /**
   * Lists the single descendant of a branch with one child.
   */
  #[Test]
  public function descendantsOfBranchWithSingleChild(): void {
    $tree = $this->buildTree();
    self::assertSame([6], $tree->descendantsOf(3));
  }

  /**
   * Returns an empty list of descendants for a leaf node.
   */
  #[Test]
  public function descendantsOfLeafReturnsEmpty(): void {
    $tree = $this->buildTree();
    self::assertSame([], $tree->descendantsOf(6));
  }

  /**
   * Returns only itself as the descendant list for a leaf node.
   */
  #[Test]
  public function descendantsOfLeafWithSelfReturnsSelf(): void {
    $tree = $this->buildTree();
    self::assertSame([6], $tree->descendantsOf(6, TRUE));
  }

  /**
   * Returns an empty list of descendants for an unknown id.
   */
  #[Test]
  public function descendantsOfUnknownReturnsEmpty(): void {
    $tree = $this->buildTree();
    self::assertSame([], $tree->descendantsOf(99));
  }

  /**
   * Returns only itself as the descendant list for an unknown id.
   */
  #[Test]
  public function descendantsOfUnknownWithSelfReturnsOnlySelf(): void {
    $tree = $this->buildTree();
    self::assertSame([99], $tree->descendantsOf(99, TRUE));
  }

}
