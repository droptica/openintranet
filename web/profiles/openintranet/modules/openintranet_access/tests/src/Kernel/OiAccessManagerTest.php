<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_access\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the OiAccessManager service.
 *
 * @coversDefaultClass \Drupal\openintranet_access\Service\OiAccessManager
 * @group openintranet_access
 */
class OiAccessManagerTest extends OiAccessKernelTestBase {

  /**
   * A test node for access management.
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
   * Tests setting access groups.
   *
   * @covers ::setAccessGroups
   */
  public function testSetAccessGroups(): void {
    $group1 = $this->createOiGroup(['name' => 'Group 1']);
    $group2 = $this->createOiGroup(['name' => 'Group 2']);

    $this->accessManager->setAccessGroups($this->testNode, [$group1, $group2]);

    $accessGroups = $this->accessChecker->getAccessGroups($this->testNode);
    $this->assertCount(2, $accessGroups);
  }

  /**
   * Tests that setAccessGroups replaces existing groups.
   *
   * @covers ::setAccessGroups
   */
  public function testSetAccessGroupsReplaces(): void {
    $group1 = $this->createOiGroup(['name' => 'Group 1']);
    $group2 = $this->createOiGroup(['name' => 'Group 2']);
    $group3 = $this->createOiGroup(['name' => 'Group 3']);

    // Set initial groups.
    $this->accessManager->setAccessGroups($this->testNode, [$group1, $group2]);
    $this->assertCount(2, $this->accessChecker->getAccessGroups($this->testNode));

    // Replace with different group.
    $this->accessManager->setAccessGroups($this->testNode, [$group3]);

    $accessGroups = $this->accessChecker->getAccessGroups($this->testNode);
    $this->assertCount(1, $accessGroups);

    $groupIds = array_map(fn($g) => $g->id(), $accessGroups);
    $this->assertContains($group3->id(), $groupIds);
    $this->assertNotContains($group1->id(), $groupIds);
    $this->assertNotContains($group2->id(), $groupIds);
  }

  /**
   * Tests adding a single access group.
   *
   * @covers ::addAccessGroup
   */
  public function testAddAccessGroup(): void {
    $group1 = $this->createOiGroup(['name' => 'Group 1']);
    $group2 = $this->createOiGroup(['name' => 'Group 2']);

    $this->accessManager->addAccessGroup($this->testNode, $group1);
    $this->assertCount(1, $this->accessChecker->getAccessGroups($this->testNode));

    $this->accessManager->addAccessGroup($this->testNode, $group2);
    $this->assertCount(2, $this->accessChecker->getAccessGroups($this->testNode));
  }

  /**
   * Tests removing an access group.
   *
   * @covers ::removeAccessGroup
   */
  public function testRemoveAccessGroup(): void {
    $group1 = $this->createOiGroup(['name' => 'Group 1']);
    $group2 = $this->createOiGroup(['name' => 'Group 2']);

    $this->accessManager->setAccessGroups($this->testNode, [$group1, $group2]);
    $this->assertCount(2, $this->accessChecker->getAccessGroups($this->testNode));

    $this->accessManager->removeAccessGroup($this->testNode, $group1);

    $accessGroups = $this->accessChecker->getAccessGroups($this->testNode);
    $this->assertCount(1, $accessGroups);

    $groupIds = array_map(fn($g) => $g->id(), $accessGroups);
    $this->assertNotContains($group1->id(), $groupIds);
    $this->assertContains($group2->id(), $groupIds);
  }

  /**
   * Tests setting access users.
   *
   * @covers ::setAccessUsers
   */
  public function testSetAccessUsers(): void {
    $user1 = $this->createUser();
    $user2 = $this->createUser();

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
   * Tests adding a single access user.
   *
   * @covers ::addAccessUser
   */
  public function testAddAccessUser(): void {
    $user1 = $this->createUser();
    $user2 = $this->createUser();

    $this->accessManager->addAccessUser($this->testNode, (int) $user1->id());
    $this->assertCount(1, $this->accessChecker->getAccessUserIds($this->testNode));

    $this->accessManager->addAccessUser($this->testNode, (int) $user2->id());
    $this->assertCount(2, $this->accessChecker->getAccessUserIds($this->testNode));
  }

  /**
   * Tests removing an access user.
   *
   * @covers ::removeAccessUser
   */
  public function testRemoveAccessUser(): void {
    $user1 = $this->createUser();
    $user2 = $this->createUser();

    $this->accessManager->setAccessUsers($this->testNode, [
      (int) $user1->id(),
      (int) $user2->id(),
    ]);

    $this->accessManager->removeAccessUser($this->testNode, (int) $user1->id());

    $userIds = $this->accessChecker->getAccessUserIds($this->testNode);
    $this->assertCount(1, $userIds);
    $this->assertNotContains((int) $user1->id(), $userIds);
    $this->assertContains((int) $user2->id(), $userIds);
  }

  /**
   * Tests clearing all access restrictions.
   *
   * @covers ::clearAccessRestrictions
   */
  public function testClearAccessRestrictions(): void {
    $group = $this->createOiGroup(['name' => 'Test Group']);
    $user = $this->createUser();

    $this->accessManager->setAccessGroups($this->testNode, [$group]);
    $this->accessManager->addAccessUser($this->testNode, (int) $user->id());

    $this->assertTrue($this->accessChecker->hasRestrictions($this->testNode));

    $this->accessManager->clearAccessRestrictions($this->testNode);

    $this->assertFalse($this->accessChecker->hasRestrictions($this->testNode));
    $this->assertEmpty($this->accessChecker->getAccessGroups($this->testNode));
    $this->assertEmpty($this->accessChecker->getAccessUserIds($this->testNode));
  }

  /**
   * Tests copying access settings between entities.
   *
   * @covers ::copyAccessSettings
   */
  public function testCopyAccessSettings(): void {
    $group = $this->createOiGroup(['name' => 'Test Group']);
    $user = $this->createUser();

    // Set access on source node.
    $this->accessManager->setAccessGroups($this->testNode, [$group]);
    $this->accessManager->addAccessUser($this->testNode, (int) $user->id());

    // Create target node.
    $targetNode = Node::create([
      'type' => 'page',
      'title' => 'Target Page',
      'status' => 1,
      'uid' => $this->getCurrentUser()->id(),
    ]);
    $targetNode->save();

    // Copy access settings.
    $this->accessManager->copyAccessSettings($this->testNode, $targetNode);

    // Verify target has same restrictions.
    $this->assertTrue($this->accessChecker->hasRestrictions($targetNode));

    $targetGroups = $this->accessChecker->getAccessGroups($targetNode);
    $this->assertCount(1, $targetGroups);

    $targetUserIds = $this->accessChecker->getAccessUserIds($targetNode);
    $this->assertCount(1, $targetUserIds);
    $this->assertContains((int) $user->id(), $targetUserIds);
  }

  /**
   * Tests that adding same group twice doesn't create duplicates.
   *
   * @covers ::addAccessGroup
   */
  public function testAddAccessGroupTwice(): void {
    $group = $this->createOiGroup(['name' => 'Test Group']);

    $this->accessManager->addAccessGroup($this->testNode, $group);
    $this->accessManager->addAccessGroup($this->testNode, $group);

    $accessGroups = $this->accessChecker->getAccessGroups($this->testNode);
    $this->assertCount(1, $accessGroups);
  }

  /**
   * Tests that adding same user twice doesn't create duplicates.
   *
   * @covers ::addAccessUser
   */
  public function testAddAccessUserTwice(): void {
    $user = $this->createUser();

    $this->accessManager->addAccessUser($this->testNode, (int) $user->id());
    $this->accessManager->addAccessUser($this->testNode, (int) $user->id());

    $userIds = $this->accessChecker->getAccessUserIds($this->testNode);
    $this->assertCount(1, $userIds);
  }

}
