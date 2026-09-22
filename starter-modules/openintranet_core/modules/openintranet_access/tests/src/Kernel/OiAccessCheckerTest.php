<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_access\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the OiAccessChecker service.
 *
 * @coversDefaultClass \Drupal\openintranet_access\Service\OiAccessChecker
 * @group openintranet_access
 */
class OiAccessCheckerTest extends OiAccessKernelTestBase {

  /**
   * A test node for access checking.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $testNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a node type for testing.
    $nodeType = NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ]);
    $nodeType->save();

    // Create a test node.
    $this->testNode = Node::create([
      'type' => 'page',
      'title' => 'Test Page',
      'status' => 1,
      'uid' => $this->getCurrentUser()->id(),
    ]);
    $this->testNode->save();
  }

  /**
   * Tests that entities without restrictions return NULL (allow default).
   *
   * @covers ::checkEntityAccess
   * @covers ::hasRestrictions
   */
  public function testNoRestrictions(): void {
    $user = $this->createUser();

    // No restrictions set - should return NULL (neutral).
    $result = $this->accessChecker->checkEntityAccess($this->testNode, $user, 'view');
    $this->assertNull($result);

    $this->assertFalse($this->accessChecker->hasRestrictions($this->testNode));
  }

  /**
   * Tests that group member has access to restricted content.
   *
   * @covers ::checkEntityAccess
   */
  public function testGroupMemberHasAccess(): void {
    $group = $this->createOiGroup(['name' => 'Test Group']);
    $member = $this->createUser();

    // Add user to group.
    $this->groupManager->addMember($group, $member);

    // Set access restriction to group.
    $this->accessManager->setAccessGroups($this->testNode, [$group]);

    // Member should have access.
    $result = $this->accessChecker->checkEntityAccess($this->testNode, $member, 'view');
    $this->assertTrue($result);
  }

  /**
   * Tests that non-member does not have access to restricted content.
   *
   * @covers ::checkEntityAccess
   */
  public function testNonMemberNoAccess(): void {
    $group = $this->createOiGroup(['name' => 'Test Group']);
    $nonMember = $this->createUser();

    // Set access restriction to group (don't add user).
    $this->accessManager->setAccessGroups($this->testNode, [$group]);

    // Non-member should not have access.
    $result = $this->accessChecker->checkEntityAccess($this->testNode, $nonMember, 'view');
    $this->assertFalse($result);
  }

  /**
   * Tests direct user access (individual user grant).
   *
   * @covers ::checkEntityAccess
   */
  public function testDirectUserAccess(): void {
    $allowedUser = $this->createUser();
    $notAllowedUser = $this->createUser();

    // Set direct user access.
    $this->accessManager->setAccessUsers($this->testNode, [(int) $allowedUser->id()]);

    // Allowed user has access.
    $result = $this->accessChecker->checkEntityAccess($this->testNode, $allowedUser, 'view');
    $this->assertTrue($result);

    // Not allowed user does not have access.
    $result = $this->accessChecker->checkEntityAccess($this->testNode, $notAllowedUser, 'view');
    $this->assertFalse($result);
  }

  /**
   * Tests access via subgroup (hierarchy inheritance).
   *
   * @covers ::checkEntityAccess
   */
  public function testSubgroupInheritance(): void {
    // Create parent and child groups.
    $parentGroup = $this->createOiGroup(['name' => 'Parent Company']);
    $childGroup = $this->createOiGroup([
      'name' => 'Engineering Department',
      'parent' => $parentGroup->id(),
    ]);

    // User in child group only.
    $childMember = $this->createUser();
    $this->groupManager->addMember($childGroup, $childMember);

    // Restrict content to parent group.
    $this->accessManager->setAccessGroups($this->testNode, [$parentGroup]);

    // Child group member should have access via inheritance.
    $result = $this->accessChecker->checkEntityAccess($this->testNode, $childMember, 'view');
    $this->assertTrue($result, 'User in child group should have access to parent-restricted content.');
  }

  /**
   * Tests combined group and user access.
   *
   * @covers ::checkEntityAccess
   */
  public function testCombinedGroupAndUserAccess(): void {
    $group = $this->createOiGroup(['name' => 'Test Group']);
    $groupMember = $this->createUser();
    $directUser = $this->createUser();
    $outsider = $this->createUser();

    $this->groupManager->addMember($group, $groupMember);

    // Set both group and user access.
    $this->accessManager->setAccessGroups($this->testNode, [$group]);
    $this->accessManager->addAccessUser($this->testNode, (int) $directUser->id());

    // Group member has access.
    $this->assertTrue(
      $this->accessChecker->checkEntityAccess($this->testNode, $groupMember, 'view')
    );

    // Direct user has access.
    $this->assertTrue(
      $this->accessChecker->checkEntityAccess($this->testNode, $directUser, 'view')
    );

    // Outsider does not have access.
    $this->assertFalse(
      $this->accessChecker->checkEntityAccess($this->testNode, $outsider, 'view')
    );
  }

  /**
   * Tests multiple groups access.
   *
   * @covers ::checkEntityAccess
   */
  public function testMultipleGroupsAccess(): void {
    $group1 = $this->createOiGroup(['name' => 'Group 1']);
    $group2 = $this->createOiGroup(['name' => 'Group 2']);

    $userInGroup1 = $this->createUser();
    $userInGroup2 = $this->createUser();
    $userInBoth = $this->createUser();
    $userInNeither = $this->createUser();

    $this->groupManager->addMember($group1, $userInGroup1);
    $this->groupManager->addMember($group2, $userInGroup2);
    $this->groupManager->addMember($group1, $userInBoth);
    $this->groupManager->addMember($group2, $userInBoth);

    // Restrict to both groups.
    $this->accessManager->setAccessGroups($this->testNode, [$group1, $group2]);

    // All group members have access.
    $this->assertTrue(
      $this->accessChecker->checkEntityAccess($this->testNode, $userInGroup1, 'view')
    );
    $this->assertTrue(
      $this->accessChecker->checkEntityAccess($this->testNode, $userInGroup2, 'view')
    );
    $this->assertTrue(
      $this->accessChecker->checkEntityAccess($this->testNode, $userInBoth, 'view')
    );

    // User in neither group does not have access.
    $this->assertFalse(
      $this->accessChecker->checkEntityAccess($this->testNode, $userInNeither, 'view')
    );
  }

  /**
   * Tests getting access groups for an entity.
   *
   * @covers ::getAccessGroups
   */
  public function testGetAccessGroups(): void {
    $group1 = $this->createOiGroup(['name' => 'Group 1']);
    $group2 = $this->createOiGroup(['name' => 'Group 2']);

    // No groups initially.
    $this->assertEmpty($this->accessChecker->getAccessGroups($this->testNode));

    // Set groups.
    $this->accessManager->setAccessGroups($this->testNode, [$group1, $group2]);

    $accessGroups = $this->accessChecker->getAccessGroups($this->testNode);
    $this->assertCount(2, $accessGroups);

    $groupIds = array_map(fn($g) => $g->id(), $accessGroups);
    $this->assertContains($group1->id(), $groupIds);
    $this->assertContains($group2->id(), $groupIds);
  }

  /**
   * Tests getting access user IDs for an entity.
   *
   * @covers ::getAccessUserIds
   */
  public function testGetAccessUserIds(): void {
    $user1 = $this->createUser();
    $user2 = $this->createUser();

    // No users initially.
    $this->assertEmpty($this->accessChecker->getAccessUserIds($this->testNode));

    // Set users.
    $this->accessManager->setAccessUsers($this->testNode, [
      (int) $user1->id(),
      (int) $user2->id(),
    ]);

    $userIds = $this->accessChecker->getAccessUserIds($this->testNode);
    $this->assertCount(2, $userIds);
    $this->assertContains((int) $user1->id(), $userIds);
    $this->assertContains((int) $user2->id(), $userIds);
  }

  /**
   * Tests getting all allowed user IDs (groups + direct).
   *
   * @covers ::getAllAllowedUserIds
   */
  public function testGetAllAllowedUserIds(): void {
    $group = $this->createOiGroup(['name' => 'Test Group']);
    $groupMember = $this->createUser();
    $directUser = $this->createUser();

    $this->groupManager->addMember($group, $groupMember);

    $this->accessManager->setAccessGroups($this->testNode, [$group]);
    $this->accessManager->addAccessUser($this->testNode, (int) $directUser->id());

    $allUserIds = $this->accessChecker->getAllAllowedUserIds($this->testNode);

    $this->assertContains((int) $groupMember->id(), $allUserIds);
    $this->assertContains((int) $directUser->id(), $allUserIds);
  }

}
