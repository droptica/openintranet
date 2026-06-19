<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Condition;

use Drupal\KernelTests\KernelTestBase;

/**
 * Base for ECA notification condition kernel tests.
 *
 * Installs the notification stack so the three custom conditions can be
 * instantiated via the ECA condition plugin manager and evaluated against
 * real preference / dedupe / rate-limit state.
 */
abstract class NotificationConditionKernelTestBase extends KernelTestBase {

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
   * The ECA condition plugin manager.
   *
   * @var \Drupal\eca\PluginManager\Condition
   */
  protected $conditionManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('user_notification_settings');
    $this->installConfig(['openintranet_notifications']);
    $this->conditionManager = $this->container->get('plugin.manager.eca.condition');
  }

}
