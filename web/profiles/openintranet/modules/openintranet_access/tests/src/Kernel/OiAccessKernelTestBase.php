<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_access\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\openintranet_access\Traits\OiAccessTestTrait;

/**
 * Defines an abstract test base for Open Intranet Access kernel tests.
 */
abstract class OiAccessKernelTestBase extends EntityKernelTestBase {

  use OiAccessTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'openintranet_access',
    'node',
  ];

  /**
   * The group manager service.
   *
   * @var \Drupal\openintranet_access\Service\OiGroupManagerInterface
   */
  protected $groupManager;

  /**
   * The access checker service.
   *
   * @var \Drupal\openintranet_access\Service\OiAccessCheckerInterface
   */
  protected $accessChecker;

  /**
   * The access manager service.
   *
   * @var \Drupal\openintranet_access\Service\OiAccessManagerInterface
   */
  protected $accessManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('oi_group');
    $this->installSchema('openintranet_access', [
      'oi_group_membership',
      'oi_access_record',
    ]);
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['openintranet_access', 'node']);

    $this->groupManager = $this->container->get('openintranet_access.group_manager');
    $this->accessChecker = $this->container->get('openintranet_access.checker');
    $this->accessManager = $this->container->get('openintranet_access.access_manager');

    // Make sure we do not use user 1.
    $this->createUser();
    $this->setCurrentUser($this->createUser());
  }

  /**
   * Gets the current user so you can run some checks against them.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The current user.
   */
  protected function getCurrentUser(): AccountInterface {
    return $this->container->get('current_user')->getAccount();
  }

}
