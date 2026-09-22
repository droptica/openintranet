<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Access;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Access\NotificationPreferencesAccess;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests own-only access to the preference form (§14 security, Chunk 5A).
 *
 * @group openintranet_notifications
 *
 * @coversDefaultClass \Drupal\openintranet_notifications\Access\NotificationPreferencesAccess
 */
final class NotificationPreferencesAccessTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'options',
    'dynamic_entity_reference',
    'token',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
    'openintranet_notifications',
  ];

  /**
   * The access checker under test.
   */
  private NotificationPreferencesAccess $checker;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['user']);
    // User 1 is the superuser; create it so later uids are real accounts.
    User::create(['name' => 'root', 'uid' => 1, 'status' => 1])->save();
    $this->checker = NotificationPreferencesAccess::create($this->container);
  }

  /**
   * Creates a user with the given permissions.
   *
   * @param string $name
   *   The username (also used to derive a role id).
   * @param string[] $permissions
   *   The permissions to grant.
   */
  private function makeUser(string $name, array $permissions = []): User {
    $rid = NULL;
    if ($permissions !== []) {
      $role = Role::create(['id' => $name . '_role', 'label' => $name . ' role']);
      $role->save();
      foreach ($permissions as $permission) {
        $role->grantPermission($permission);
      }
      $role->save();
      $rid = $role->id();
    }
    $values = ['name' => $name, 'status' => 1];
    if ($rid !== NULL) {
      $values['roles'] = [$rid];
    }
    $user = User::create($values);
    $user->save();
    return $user;
  }

  /**
   * The owner with the own-preferences permission is allowed.
   *
   * @covers ::access
   */
  public function testOwnerWithPermissionAllowed(): void {
    $owner = $this->makeUser('owner', ['administer own notification preferences']);
    $result = $this->checker->access($owner, $owner);
    self::assertTrue($result->isAllowed(), 'A user may edit their own preferences.');
  }

  /**
   * The owner without the permission is forbidden.
   *
   * @covers ::access
   */
  public function testOwnerWithoutPermissionForbidden(): void {
    $owner = $this->makeUser('plain');
    $result = $this->checker->access($owner, $owner);
    self::assertFalse($result->isAllowed(), 'Without the permission even the owner is denied.');
  }

  /**
   * A different non-admin user cannot reach another user's preferences.
   *
   * @covers ::access
   */
  public function testOtherNonAdminForbidden(): void {
    $target = $this->makeUser('target', ['administer own notification preferences']);
    $other = $this->makeUser('other', ['administer own notification preferences']);
    $result = $this->checker->access($target, $other);
    self::assertFalse($result->isAllowed(), 'A user must NOT open another user\'s preferences.');
  }

  /**
   * An administrator with "administer users" may open any user's preferences.
   *
   * @covers ::access
   */
  public function testAdminAllowed(): void {
    $target = $this->makeUser('target2');
    $admin = $this->makeUser('admin', ['administer users']);
    $result = $this->checker->access($target, $admin);
    self::assertTrue($result->isAllowed(), 'An administrator may manage any user.');
  }

  /**
   * Anonymous is forbidden.
   *
   * @covers ::access
   */
  public function testAnonymousForbidden(): void {
    $target = $this->makeUser('target3', ['administer own notification preferences']);
    $anonymous = User::getAnonymousUser();
    $result = $this->checker->access($target, $anonymous);
    self::assertFalse($result->isAllowed(), 'Anonymous must be denied.');
  }

}
