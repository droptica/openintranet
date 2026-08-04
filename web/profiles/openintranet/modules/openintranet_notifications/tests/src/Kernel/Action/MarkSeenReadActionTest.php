<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Action;

use Drupal\openintranet_notifications\Event\NotificationEvents;
use Drupal\openintranet_notifications\Event\NotificationSeenEvent;

/**
 * Tests the mark_seen_read ECA action.
 *
 * @group openintranet_notifications
 */
final class MarkSeenReadActionTest extends NotificationActionKernelTestBase {

  /**
   * Builds and saves a notification, returning its id.
   */
  private function makeNotification(): int {
    $factory = $this->container->get('openintranet_notifications.notification_factory');
    $notification = $factory->create('default', ['uid' => 42, 'subject' => 'Hi', 'body' => 'B']);
    $notification->save();
    $this->tokenServices->addTokenData('notification', $notification);
    return (int) $notification->id();
  }

  /**
   * Mode 'seen' stamps seen_at and fires the SEEN event.
   */
  public function testSeenStampsSeenAtAndFiresEvent(): void {
    $id = $this->makeNotification();
    $seen = 0;
    $this->container->get('event_dispatcher')->addListener(
      NotificationEvents::SEEN,
      function (NotificationSeenEvent $event) use (&$seen): void {
        $seen++;
      },
    );

    $action = $this->actionManager->createInstance('openintranet_notifications_mark_seen_read', [
      'notification' => '[notification]',
      'mode' => 'seen',
    ]);
    $action->execute(NULL);

    $reloaded = $this->reloadNotification($id);
    self::assertNotNull($reloaded->get('seen_at')->value);
    self::assertNull($reloaded->get('read_at')->value);
    self::assertSame(1, $seen);
  }

  /**
   * Mode 'read' stamps read_at and does NOT fire the SEEN event.
   */
  public function testReadStampsReadAtNoSeenEvent(): void {
    $id = $this->makeNotification();
    $seen = 0;
    $this->container->get('event_dispatcher')->addListener(
      NotificationEvents::SEEN,
      function () use (&$seen): void {
        $seen++;
      },
    );

    $action = $this->actionManager->createInstance('openintranet_notifications_mark_seen_read', [
      'notification' => '[notification]',
      'mode' => 'read',
    ]);
    $action->execute(NULL);

    $reloaded = $this->reloadNotification($id);
    self::assertNotNull($reloaded->get('read_at')->value);
    self::assertNull($reloaded->get('seen_at')->value);
    self::assertSame(0, $seen);
  }

  /**
   * Mode 'both' stamps both timestamps and fires the SEEN event once.
   */
  public function testBothStampsBothAndFiresSeenOnce(): void {
    $id = $this->makeNotification();
    $seen = 0;
    $this->container->get('event_dispatcher')->addListener(
      NotificationEvents::SEEN,
      function () use (&$seen): void {
        $seen++;
      },
    );

    $action = $this->actionManager->createInstance('openintranet_notifications_mark_seen_read', [
      'notification' => '[notification]',
      'mode' => 'both',
    ]);
    $action->execute(NULL);

    $reloaded = $this->reloadNotification($id);
    self::assertNotNull($reloaded->get('seen_at')->value);
    self::assertNotNull($reloaded->get('read_at')->value);
    self::assertSame(1, $seen);
  }

}
