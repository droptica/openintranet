<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_access\Kernel;

/**
 * Tests group hierarchy functionality.
 *
 * @coversDefaultClass \Drupal\openintranet_access\Service\OiGroupManager
 * @group openintranet_access
 */
class OiGroupHierarchyTest extends OiAccessKernelTestBase {

  /**
   * Tests getting group children (direct subgroups).
   *
   * @covers ::getChildren
   */
  public function testGetGroupChildren(): void {
    $parent = $this->createOiGroup(['name' => 'Parent']);
    $child1 = $this->createOiGroup(['name' => 'Child 1', 'parent' => $parent->id()]);
    $child2 = $this->createOiGroup(['name' => 'Child 2', 'parent' => $parent->id()]);
    $grandchild = $this->createOiGroup(['name' => 'Grandchild', 'parent' => $child1->id()]);

    $children = $this->groupManager->getChildren($parent);

    $this->assertCount(2, $children);

    $childIds = array_map(fn($g) => $g->id(), $children);
    $this->assertContains($child1->id(), $childIds);
    $this->assertContains($child2->id(), $childIds);
    $this->assertNotContains($grandchild->id(), $childIds);
  }

  /**
   * Tests getting all descendants (recursive).
   *
   * @covers ::getGroupDescendants
   */
  public function testGetGroupDescendants(): void {
    $parent = $this->createOiGroup(['name' => 'Parent']);
    $child1 = $this->createOiGroup(['name' => 'Child 1', 'parent' => $parent->id()]);
    $child2 = $this->createOiGroup(['name' => 'Child 2', 'parent' => $parent->id()]);
    $grandchild1 = $this->createOiGroup(['name' => 'Grandchild 1', 'parent' => $child1->id()]);
    $grandchild2 = $this->createOiGroup(['name' => 'Grandchild 2', 'parent' => $child1->id()]);

    $descendants = $this->groupManager->getGroupDescendants($parent);

    $this->assertCount(4, $descendants);

    $descendantIds = array_map(fn($g) => $g->id(), $descendants);
    $this->assertContains($child1->id(), $descendantIds);
    $this->assertContains($child2->id(), $descendantIds);
    $this->assertContains($grandchild1->id(), $descendantIds);
    $this->assertContains($grandchild2->id(), $descendantIds);
  }

  /**
   * Tests getting user groups with ancestors.
   *
   * @covers ::getUserGroupsWithAncestors
   */
  public function testGetUserGroupsWithAncestors(): void {
    $grandparent = $this->createOiGroup(['name' => 'Grandparent']);
    $parent = $this->createOiGroup(['name' => 'Parent', 'parent' => $grandparent->id()]);
    $child = $this->createOiGroup(['name' => 'Child', 'parent' => $parent->id()]);

    $user = $this->createUser();
    $this->groupManager->addMember($child, $user);

    $userGroupsWithAncestors = $this->groupManager->getUserGroupsWithAncestors($user);

    $groupIds = array_map(fn($g) => $g->id(), $userGroupsWithAncestors);

    // User is in child, but should also get parent and grandparent.
    $this->assertContains($child->id(), $groupIds);
    $this->assertContains($parent->id(), $groupIds);
    $this->assertContains($grandparent->id(), $groupIds);
  }

  /**
   * Tests getting group ancestors.
   *
   * @covers ::getAncestors
   */
  public function testGetGroupAncestors(): void {
    $level1 = $this->createOiGroup(['name' => 'Level 1']);
    $level2 = $this->createOiGroup(['name' => 'Level 2', 'parent' => $level1->id()]);
    $level3 = $this->createOiGroup(['name' => 'Level 3', 'parent' => $level2->id()]);
    $level4 = $this->createOiGroup(['name' => 'Level 4', 'parent' => $level3->id()]);

    $ancestors = $this->groupManager->getAncestors($level4);

    $this->assertCount(3, $ancestors);

    $ancestorIds = array_map(fn($g) => $g->id(), $ancestors);
    $this->assertContains($level3->id(), $ancestorIds);
    $this->assertContains($level2->id(), $ancestorIds);
    $this->assertContains($level1->id(), $ancestorIds);
  }

  /**
   * Tests that root groups have no ancestors.
   *
   * @covers ::getAncestors
   */
  public function testRootGroupNoAncestors(): void {
    $rootGroup = $this->createOiGroup(['name' => 'Root']);

    $ancestors = $this->groupManager->getAncestors($rootGroup);

    $this->assertEmpty($ancestors);
  }

  /**
   * Tests that root groups have no parent.
   */
  public function testRootGroupNoParent(): void {
    $rootGroup = $this->createOiGroup(['name' => 'Root']);

    $this->assertNull($rootGroup->getParent());
  }

  /**
   * Tests getting members including subgroups.
   *
   * @covers ::getGroupMembers
   */
  public function testGetMembersIncludingSubgroups(): void {
    $parent = $this->createOiGroup(['name' => 'Parent']);
    $child = $this->createOiGroup(['name' => 'Child', 'parent' => $parent->id()]);

    $parentMember = $this->createUser();
    $childMember = $this->createUser();

    $this->groupManager->addMember($parent, $parentMember);
    $this->groupManager->addMember($child, $childMember);

    // Get members with subgroups.
    $allMembers = $this->groupManager->getGroupMembers($parent, TRUE);

    $memberIds = array_map(fn($m) => $m->id(), $allMembers);
    $this->assertContains($parentMember->id(), $memberIds);
    $this->assertContains($childMember->id(), $memberIds);
  }

  /**
   * Tests deep hierarchy (many levels).
   */
  public function testDeepHierarchy(): void {
    $levels = [];
    $parent = NULL;

    // Create 5-level hierarchy.
    for ($i = 1; $i <= 5; $i++) {
      $values = ['name' => "Level $i"];
      if ($parent) {
        $values['parent'] = $parent->id();
      }
      $levels[$i] = $this->createOiGroup($values);
      $parent = $levels[$i];
    }

    // User in deepest level.
    $user = $this->createUser();
    $this->groupManager->addMember($levels[5], $user);

    // Should get all ancestors.
    $userGroupsWithAncestors = $this->groupManager->getUserGroupsWithAncestors($user);
    $this->assertCount(5, $userGroupsWithAncestors);
  }

}
