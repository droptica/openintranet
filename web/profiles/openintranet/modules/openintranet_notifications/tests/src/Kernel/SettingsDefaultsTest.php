<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the global settings shipped in config/install.
 *
 * @group openintranet_notifications
 */
final class SettingsDefaultsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // The system and user modules provide the "action"/"user" entity types
    // ECA's action plugin manager and token data providers resolve while
    // rebuilding the container during module install.
    'system',
    'user',
    'openintranet_notifications',
    'dynamic_entity_reference',
    'options',
    'token',
    'key',
    // ECA (eca:eca) depends on modeler_api:modeler_api (ECA 3.1.x); without it
    // the eca.processor service references a non-existent
    // template_token_resolver.
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['openintranet_notifications']);
  }

  /**
   * The settings install file ships the documented defaults.
   */
  public function testSettingsDefaults(): void {
    $settings = $this->config('openintranet_notifications.settings');

    self::assertSame(['inbox', 'log_only'], $settings->get('enabled_channels'));
    self::assertSame(['default'], $settings->get('enabled_types'));
    self::assertSame(
      ['default' => ['inbox' => TRUE, 'log_only' => FALSE]],
      $settings->get('default_user_preferences'),
    );
    self::assertSame('openintranet_notification_delivery', $settings->get('queue.id'));
    self::assertSame(90, $settings->get('retention.default_days'));
    // Nothing is killed by default: the per-channel kill switch map is empty.
    self::assertSame([], $settings->get('kill_switch'));
  }

}
