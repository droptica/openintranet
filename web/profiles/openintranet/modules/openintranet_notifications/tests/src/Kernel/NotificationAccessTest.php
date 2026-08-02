<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\Notification;
use Drupal\user\Entity\User;

/**
 * Tests access control on the openintranet_notification entity.
 *
 * @group openintranet_notifications
 */
final class NotificationAccessTest extends KernelTestBase {

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
   * A recipient-less (uid 0) notification must not be owned by anonymous.
   */
  public function testAnonymousIsNotOwnerOfRecipientlessNotification(): void {
    $notification = Notification::create([
      'type' => 'default',
      'subject' => 'x',
      'body' => 'y',
      'status' => 'created',
      'priority' => 'normal',
    ]);
    $notification->save();

    $anonymous = new AnonymousUserSession();
    self::assertFalse($notification->access('view', $anonymous));
    self::assertFalse($notification->access('update', $anonymous));
  }

  /**
   * The recipient may view/update their own notification; others may not.
   */
  public function testRecipientOwnsItsNotification(): void {
    $owner = User::create(['name' => 'owner']);
    $owner->save();
    $other = User::create(['name' => 'other']);
    $other->save();

    $notification = Notification::create([
      'type' => 'default',
      'uid' => $owner->id(),
      'subject' => 'x',
      'body' => 'y',
      'status' => 'created',
      'priority' => 'normal',
    ]);
    $notification->save();

    self::assertTrue($notification->access('view', $owner));
    self::assertTrue($notification->access('update', $owner));
    self::assertFalse($notification->access('view', $other));
    self::assertFalse($notification->access('update', $other));
  }

}
