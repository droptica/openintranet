<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests channel plugin discovery via the channel plugin manager.
 *
 * @group openintranet_notifications
 */
final class ChannelPluginManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'openintranet_notifications',
    'dynamic_entity_reference',
    'options',
    'token',
    'key',
    // eca:eca depends on modeler_api:modeler_api (ECA 3.1.x); without it the
    // eca.processor service references a non-existent template_token_resolver.
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
  ];

  /**
   * The null and log_only channels are discovered and instantiable.
   */
  public function testNullAndLogChannelsAreDiscovered(): void {
    $manager = $this->container->get('plugin.manager.openintranet_notification_channel');
    $defs = $manager->getDefinitions();
    self::assertArrayHasKey('null', $defs);
    self::assertArrayHasKey('log_only', $defs);
    $null = $manager->createInstance('null');
    self::assertTrue($null->isAvailable());
  }

  /**
   * Selectable definitions hide the internal null channel but keep real ones.
   */
  public function testSelectableDefinitionsExcludeInternalChannels(): void {
    $manager = $this->container->get('plugin.manager.openintranet_notification_channel');
    $selectable = $manager->getSelectableDefinitions();

    self::assertArrayNotHasKey('null', $selectable, 'The no-op null channel is not offered as a choice.');
    self::assertArrayHasKey('inbox', $selectable);
    self::assertArrayHasKey('email_core', $selectable);
    self::assertArrayHasKey('log_only', $selectable);
    // Still discoverable for the read-only status page / runtime delivery.
    self::assertArrayHasKey('null', $manager->getDefinitions());
  }

}
