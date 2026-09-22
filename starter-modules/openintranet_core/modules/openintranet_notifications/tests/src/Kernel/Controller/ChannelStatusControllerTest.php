<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Controller;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Controller\ChannelStatusController;

/**
 * Tests the read-only channel-status admin page (§13 "UI statusu kanałów").
 *
 * @group openintranet_notifications
 *
 * @coversDefaultClass \Drupal\openintranet_notifications\Controller\ChannelStatusController
 */
final class ChannelStatusControllerTest extends KernelTestBase {

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
    'key',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
    'openintranet_notifications',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['openintranet_notifications']);
  }

  /**
   * The overview is a table with a row per discovered channel.
   *
   * @covers ::overview
   * @covers ::create
   */
  public function testOverviewListsChannels(): void {
    $build = $this->controller()->overview();

    self::assertSame('table', $build['#type']);
    self::assertCount(5, $build['#header']);

    $rows = $this->rowsByChannelId($build);
    foreach (['email_core', 'webhook', 'inbox', 'log_only', 'null'] as $id) {
      self::assertArrayHasKey($id, $rows, "Channel $id is listed.");
    }
  }

  /**
   * The enabled column reflects settings.enabled_channels (inbox + log_only).
   *
   * @covers ::overview
   */
  public function testEnabledColumnReflectsSettings(): void {
    $rows = $this->rowsByChannelId($this->controller()->overview());

    self::assertSame('Yes', (string) $rows['inbox'][3]);
    self::assertSame('Yes', (string) $rows['log_only'][3]);
    self::assertSame('No', (string) $rows['null'][3]);
  }

  /**
   * The availability column reflects each channel's isAvailable().
   *
   * The webhook channel is unavailable out of the box (no URL configured); the
   * no-op channels are available.
   *
   * @covers ::overview
   */
  public function testAvailabilityColumn(): void {
    $rows = $this->rowsByChannelId($this->controller()->overview());

    self::assertSame('Yes', (string) $rows['null'][2]);
    self::assertSame('Yes', (string) $rows['log_only'][2]);
    self::assertSame('No', (string) $rows['webhook'][2]);
  }

  /**
   * A configured kill switch flips the kill-switch cell to "Killed".
   *
   * @covers ::overview
   */
  public function testKillSwitchColumnReflectsConfig(): void {
    $this->config('openintranet_notifications.settings')
      ->set('kill_switch.log_only', TRUE)
      ->save();

    $rows = $this->rowsByChannelId($this->controller()->overview());

    self::assertSame('Killed', (string) $rows['log_only'][4]);
    self::assertSame('Active', (string) $rows['null'][4]);
  }

  /**
   * Builds the controller via its create() container factory.
   */
  private function controller(): ChannelStatusController {
    return ChannelStatusController::create($this->container);
  }

  /**
   * Indexes the table rows by their channel id (the second cell).
   *
   * @return array<string, array>
   *   Rows keyed by channel id.
   */
  private function rowsByChannelId(array $build): array {
    $indexed = [];
    foreach ($build['#rows'] as $row) {
      $indexed[(string) $row[1]] = $row;
    }
    return $indexed;
  }

}
