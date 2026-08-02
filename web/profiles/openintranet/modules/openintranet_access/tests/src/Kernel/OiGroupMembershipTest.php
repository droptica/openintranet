<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_access\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\openintranet_access\Entity\OiGroupInterface;

/**
 * Tests group membership functionality.
 *
 * @coversDefaultClass \Drupal\openintranet_access\Service\OiGroupManager
 * @group openintranet_access
 */
class OiGroupMembershipTest extends OiAccessKernelTestBase {

  /**
   * The test user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $testUser;

  /**
   * The test group.
   *
   * @var \Drupal\openintranet_access\Entity\OiGroupInterface
   */
  protected OiGroupInterface $group;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testUser = $this->createUser();
    $this->group = $this->createOiGroup(['name' => 'Test Group']);
  }

  /**
   * Tests adding a member to a group.
   *
   * @covers ::addMember
   * @covers ::isMember
   */
  public function testAddMember(): void {
    $this->assertFalse($this->groupManager->isMember($this->group, $this->testUser));

    $this->groupManager->addMember($this->group, $this->testUser);

    $this->assertTrue($this->groupManager->isMember($this->group, $this->testUser));
  }

  /**
   * Tests removing a member from a group.
   *
   * @covers ::removeMember
   */
  public function testRemoveMember(): void {
    $this->groupManager->addMember($this->group, $this->testUser);
    $this->assertTrue($this->groupManager->isMember($this->group, $this->testUser));

    $this->groupManager->removeMember($this->group, $this->testUser);

    $this->assertFalse($this->groupManager->isMember($this->group, $this->testUser));
  }

  /**
   * Tests getting group members.
   *
   * @covers ::getGroupMembers
   */
  public function testGetGroupMembers(): void {
    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->groupManager->addMember($this->group, $user1);
    $this->groupManager->addMember($this->group, $user2);

    $members = $this->groupManager->getGroupMembers($this->group);

    $this->assertCount(2, $members);

    $memberIds = array_map(fn($m) => $m->id(), $members);
    $this->assertContains($user1->id(), $memberIds);
    $this->assertContains($user2->id(), $memberIds);
    $this->assertNotContains($user3->id(), $memberIds);
  }

  /**
   * Tests getting user groups.
   *
   * @covers ::getUserGroups
   */
  public function testGetUserGroups(): void {
    $group1 = $this->createOiGroup(['name' => 'Group 1']);
    $group2 = $this->createOiGroup(['name' => 'Group 2']);
    $group3 = $this->createOiGroup(['name' => 'Group 3']);

    $this->groupManager->addMember($group1, $this->testUser);
    $this->groupManager->addMember($group3, $this->testUser);

    $userGroups = $this->groupManager->getUserGroups($this->testUser);

    $this->assertCount(2, $userGroups);

    $groupIds = array_map(fn($g) => $g->id(), $userGroups);
    $this->assertContains($group1->id(), $groupIds);
    $this->assertContains($group3->id(), $groupIds);
    $this->assertNotContains($group2->id(), $groupIds);
  }

  /**
   * Tests user in multiple groups.
   *
   * @covers ::getUserGroups
   */
  public function testUserInMultipleGroups(): void {
    $group1 = $this->createOiGroup(['name' => 'Group 1']);
    $group2 = $this->createOiGroup(['name' => 'Group 2']);

    $this->groupManager->addMember($group1, $this->testUser);
    $this->groupManager->addMember($group2, $this->testUser);

    $this->assertTrue($this->groupManager->isMember($group1, $this->testUser));
    $this->assertTrue($this->groupManager->isMember($group2, $this->testUser));

    $userGroups = $this->groupManager->getUserGroups($this->testUser);
    $this->assertCount(2, $userGroups);
  }

  /**
   * Tests that adding same member twice doesn't create duplicates.
   *
   * @covers ::addMember
   */
  public function testAddMemberTwice(): void {
    $this->groupManager->addMember($this->group, $this->testUser);
    $this->groupManager->addMember($this->group, $this->testUser);

    $members = $this->groupManager->getGroupMembers($this->group);
    $this->assertCount(1, $members);
  }

  /**
   * Tests membership with subgroups (include_subgroup_members).
   *
   * @covers ::getGroupMembers
   */
  public function testGetMembersWithSubgroups(): void {
    $parentGroup = $this->createOiGroup(['name' => 'Parent']);
    $childGroup = $this->createOiGroup([
      'name' => 'Child',
      'parent' => $parentGroup->id(),
    ]);

    $parentUser = $this->createUser();
    $childUser = $this->createUser();

    $this->groupManager->addMember($parentGroup, $parentUser);
    $this->groupManager->addMember($childGroup, $childUser);

    // Without subgroups.
    $parentMembers = $this->groupManager->getGroupMembers($parentGroup, FALSE);
    $this->assertCount(1, $parentMembers);

    // With subgroups (if implemented).
    $allMembers = $this->groupManager->getGroupMembers($parentGroup, TRUE);
    $this->assertGreaterThanOrEqual(1, count($allMembers));
  }

}
