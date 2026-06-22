<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Entity;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\NotificationType;

/**
 * Tests the notification_type list builder (Chunk 4A).
 *
 * @group openintranet_notifications
 *
 * @coversDefaultClass \Drupal\openintranet_notifications\Entity\Handler\NotificationTypeListBuilder
 */
final class NotificationTypeListBuilderTest extends KernelTestBase {

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
   * The list builder builds a header and a row per notification type.
   *
   * @covers ::buildHeader
   * @covers ::buildRow
   */
  public function testListBuilderRendersRows(): void {
    NotificationType::create([
      'id' => 'mention',
      'label' => 'Mention',
      'category' => 'social',
      'default_priority' => 'high',
      'default_channels' => ['inbox', 'email_core'],
      'delivery_policy' => 'user_preferences',
      'enabled' => TRUE,
    ])->save();
    NotificationType::create([
      'id' => 'digest',
      'label' => 'Digest',
      'category' => 'system',
      'default_priority' => 'low',
      'default_channels' => ['email_core'],
      'delivery_policy' => 'user_preferences',
      'enabled' => FALSE,
    ])->save();

    $list_builder = $this->container->get('entity_type.manager')
      ->getListBuilder('openintranet_notification_type');

    $header = $list_builder->buildHeader();
    self::assertArrayHasKey('label', $header);
    self::assertArrayHasKey('id', $header);
    self::assertArrayHasKey('category', $header);
    self::assertArrayHasKey('default_priority', $header);
    self::assertArrayHasKey('default_channels', $header);
    self::assertArrayHasKey('delivery_policy', $header);
    self::assertArrayHasKey('enabled', $header);

    $mention = NotificationType::load('mention');
    $row = $list_builder->buildRow($mention);
    self::assertSame('Mention', (string) $row['label']);
    self::assertSame('mention', (string) $row['id']);
    self::assertSame('social', (string) $row['category']);
    self::assertSame('high', (string) $row['default_priority']);
    self::assertSame('inbox, email_core', (string) $row['default_channels']);
    self::assertSame('user_preferences', (string) $row['delivery_policy']);
    self::assertSame('Yes', (string) $row['enabled']);

    $digest = NotificationType::load('digest');
    $digest_row = $list_builder->buildRow($digest);
    self::assertSame('No', (string) $digest_row['enabled']);

    // The render() listing contains a row per type.
    $build = $list_builder->render();
    self::assertArrayHasKey('mention', $build['table']['#rows']);
    self::assertArrayHasKey('digest', $build['table']['#rows']);
  }

}
