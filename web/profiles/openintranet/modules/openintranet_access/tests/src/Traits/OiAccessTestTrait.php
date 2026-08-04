<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_access\Traits;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\openintranet_access\Entity\OiGroupInterface;
use Drupal\user\UserInterface;

/**
 * Provides common helper methods for Open Intranet Access tests.
 */
trait OiAccessTestTrait {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates an OiGroup.
   *
   * @param array $values
   *   (optional) The values used to create the entity.
   *
   * @return \Drupal\openintranet_access\Entity\OiGroupInterface
   *   The created group entity.
   */
  protected function createOiGroup(array $values = []): OiGroupInterface {
    $storage = $this->entityTypeManager()->getStorage('oi_group');
    $group = $storage->create($values + [
      'name' => $this->randomString(),
      'status' => TRUE,
    ]);
    $group->enforceIsNew();
    $storage->save($group);
    return $group;
  }

  /**
   * Creates a user with specific groups.
   *
   * @param array $permissions
   *   (optional) Array of permissions.
   * @param array $groups
   *   (optional) Array of OiGroup entities to add user to.
   *
   * @return \Drupal\user\UserInterface
   *   The created user.
   */
  protected function createUserWithGroups(array $permissions = [], array $groups = []): UserInterface {
    $user = $this->createUser($permissions);

    if (!empty($groups)) {
      $groupManager = \Drupal::service('openintranet_access.group_manager');
      foreach ($groups as $group) {
        $groupManager->addMember($group, $user);
      }
    }

    return $user;
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function entityTypeManager(): EntityTypeManagerInterface {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }
    return $this->entityTypeManager;
  }

}
