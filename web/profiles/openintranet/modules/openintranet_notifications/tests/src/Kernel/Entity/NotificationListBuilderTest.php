<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Entity;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\Notification;

/**
 * Tests the notification list builder (Chunk 4B).
 *
 * @group openintranet_notifications
 *
 * @coversDefaultClass \Drupal\openintranet_notifications\Entity\Handler\NotificationListBuilder
 */
final class NotificationListBuilderTest extends KernelTestBase {

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
    'views',
    'openintranet_notifications',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('openintranet_notification');
    // The 'short' date format used by the list builder lives in system config.
    $this->installConfig(['system']);
  }

  /**
   * The header exposes every documented column.
   *
   * @covers ::buildHeader
   */
  public function testHeaderHasExpectedColumns(): void {
    $list_builder = $this->container->get('entity_type.manager')
      ->getListBuilder('openintranet_notification');
    $header = $list_builder->buildHeader();
    foreach (['id', 'type', 'uid', 'subject', 'priority', 'status', 'created', 'read'] as $key) {
      self::assertArrayHasKey($key, $header);
    }
  }

  /**
   * Rows carry the entity values, with read rendered Yes/No from read_at.
   *
   * @covers ::buildRow
   */
  public function testRowRendersColumnsAndReadState(): void {
    $unread = Notification::create([
      'type' => 'mention',
      'uid' => 42,
      'subject' => 'You were mentioned',
      'priority' => 'high',
      'status' => 'delivered',
    ]);
    $unread->save();

    $read = Notification::create([
      'type' => 'digest',
      'uid' => 7,
      'subject' => 'Weekly digest',
      'priority' => 'low',
      'status' => 'delivered',
    ]);
    $read->setRead();
    $read->save();

    $list_builder = $this->container->get('entity_type.manager')
      ->getListBuilder('openintranet_notification');

    $unread_row = $list_builder->buildRow($unread);
    self::assertSame($unread->id(), $unread_row['id']);
    self::assertSame('mention', (string) $unread_row['type']);
    self::assertSame('42', (string) $unread_row['uid']);
    self::assertSame('You were mentioned', (string) $unread_row['subject']);
    self::assertSame('high', (string) $unread_row['priority']);
    self::assertSame('delivered', (string) $unread_row['status']);
    self::assertNotSame('', (string) $unread_row['created']);
    self::assertSame('No', (string) $unread_row['read']);

    $read_row = $list_builder->buildRow($read);
    self::assertSame('Yes', (string) $read_row['read']);
  }

}
