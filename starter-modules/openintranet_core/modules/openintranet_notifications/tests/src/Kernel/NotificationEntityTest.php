<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\Notification;
use Drupal\openintranet_notifications\Entity\NotificationInterface;

/**
 * Tests CRUD and helpers of the openintranet_notification content entity.
 *
 * @group openintranet_notifications
 */
final class NotificationEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'openintranet_notifications',
    'dynamic_entity_reference',
    'options',
    'text',
    'token',
    'key',
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
    $this->installEntitySchema('user');
    $this->installEntitySchema('openintranet_notification');
  }

  /**
   * A saved notification round-trips and read_at/seen_at start NULL (unread).
   */
  public function testCreateSaveReloadAndReadHelpers(): void {
    $notification = Notification::create([
      'type' => 'default',
      'uid' => 42,
      'subject' => 'Hi',
      'body' => 'Body text',
      'status' => 'created',
      'priority' => 'normal',
    ]);
    $notification->save();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification');
    $storage->resetCache();
    /** @var \Drupal\openintranet_notifications\Entity\NotificationInterface $reloaded */
    $reloaded = $storage->load($notification->id());

    self::assertInstanceOf(NotificationInterface::class, $reloaded);
    self::assertSame('default', $reloaded->get('type')->value);
    self::assertSame('created', $reloaded->get('status')->value);
    self::assertSame('normal', $reloaded->get('priority')->value);
    // Unread and unseen out of the box.
    self::assertNull($reloaded->get('read_at')->value);
    self::assertNull($reloaded->get('seen_at')->value);
    self::assertFalse($reloaded->isRead());

    // setRead() stamps read_at and isRead() flips to TRUE.
    $reloaded->setRead();
    self::assertNotNull($reloaded->get('read_at')->value);
    self::assertGreaterThan(0, (int) $reloaded->get('read_at')->value);
    self::assertTrue($reloaded->isRead());

    // setSeen() stamps seen_at.
    $reloaded->setSeen();
    self::assertNotNull($reloaded->get('seen_at')->value);
    self::assertGreaterThan(0, (int) $reloaded->get('seen_at')->value);
  }

  /**
   * The digested marker starts NULL and markDigested() stamps it once.
   */
  public function testDigestedHelpers(): void {
    $notification = Notification::create([
      'type' => 'default',
      'uid' => 7,
      'subject' => 'Digest me',
      'status' => 'created',
      'priority' => 'normal',
    ]);
    $notification->save();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification');
    $storage->resetCache();
    /** @var \Drupal\openintranet_notifications\Entity\NotificationInterface $reloaded */
    $reloaded = $storage->load($notification->id());

    // Not yet included in a digest out of the box.
    self::assertNull($reloaded->get('digested')->value);
    self::assertFalse($reloaded->isDigested());

    // markDigested() stamps the marker and isDigested() flips to TRUE.
    $reloaded->markDigested();
    self::assertNotNull($reloaded->get('digested')->value);
    self::assertGreaterThan(0, (int) $reloaded->get('digested')->value);
    self::assertTrue($reloaded->isDigested());
  }

}
