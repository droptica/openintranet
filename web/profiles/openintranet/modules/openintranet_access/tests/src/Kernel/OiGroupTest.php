<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_access\Kernel;

use Drupal\openintranet_access\Entity\OiGroup;

/**
 * Tests the OiGroup entity.
 *
 * @coversDefaultClass \Drupal\openintranet_access\Entity\OiGroup
 * @group openintranet_access
 */
class OiGroupTest extends OiAccessKernelTestBase {

  /**
   * Tests the creation of a group.
   *
   * @covers ::create
   */
  public function testCreateGroup(): void {
    $group = $this->createOiGroup(['name' => 'Test Group']);

    $this->assertNotNull($group->id());
    $this->assertEquals('Test Group', $group->label());
    $this->assertTrue($group->isActive());
  }

  /**
   * Tests group with parent (hierarchy).
   *
   * @covers ::getParent
   * @covers ::setParent
   */
  public function testGroupHierarchy(): void {
    $parentGroup = $this->createOiGroup(['name' => 'Parent Company']);
    $childGroup = $this->createOiGroup([
      'name' => 'Engineering Department',
      'parent' => $parentGroup->id(),
    ]);

    // Reload to ensure persistence.
    $childGroup = OiGroup::load($childGroup->id());

    $this->assertNotNull($childGroup->getParent());
    $this->assertEquals($parentGroup->id(), $childGroup->getParent()->id());
  }

  /**
   * Tests group status (active/inactive).
   *
   * @covers ::isActive
   * @covers ::setActive
   */
  public function testGroupStatus(): void {
    $group = $this->createOiGroup(['name' => 'Test Group', 'status' => TRUE]);
    $this->assertTrue($group->isActive());

    $group->setActive(FALSE);
    $group->save();

    // Reload and check.
    $group = OiGroup::load($group->id());
    $this->assertFalse($group->isActive());
  }

  /**
   * Tests group description.
   *
   * @covers ::getDescription
   */
  public function testGroupDescription(): void {
    $group = $this->createOiGroup(['name' => 'Test Group']);
    $this->assertNull($group->getDescription());

    $group->set('description', 'A test description');
    $group->save();

    $group = OiGroup::load($group->id());
    $this->assertEquals('A test description', $group->getDescription());
  }

  /**
   * Tests group owner.
   *
   * @covers ::getOwner
   * @covers ::getOwnerId
   */
  public function testGroupOwner(): void {
    $owner = $this->createUser();
    $group = $this->createOiGroup([
      'name' => 'Test Group',
      'uid' => $owner->id(),
    ]);

    $this->assertEquals($owner->id(), $group->getOwnerId());
    $this->assertEquals($owner->id(), $group->getOwner()->id());
  }

  /**
   * Tests group deletion.
   *
   * @covers ::delete
   */
  public function testGroupDeletion(): void {
    $group = $this->createOiGroup(['name' => 'Test Group']);
    $groupId = $group->id();

    $group->delete();

    $this->assertNull(OiGroup::load($groupId));
  }

  /**
   * Tests loading multiple groups.
   */
  public function testLoadMultiple(): void {
    $group1 = $this->createOiGroup(['name' => 'Group 1']);
    $group2 = $this->createOiGroup(['name' => 'Group 2']);
    $group3 = $this->createOiGroup(['name' => 'Group 3']);

    $groups = OiGroup::loadMultiple([$group1->id(), $group3->id()]);

    $this->assertCount(2, $groups);
    $this->assertArrayHasKey($group1->id(), $groups);
    $this->assertArrayHasKey($group3->id(), $groups);
    $this->assertArrayNotHasKey($group2->id(), $groups);
  }

}
