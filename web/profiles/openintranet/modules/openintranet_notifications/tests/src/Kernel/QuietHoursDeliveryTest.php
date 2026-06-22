<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Drupal\openintranet_notifications\Entity\UserNotificationSettings;
use Drupal\openintranet_notifications\Plugin\QueueWorker\NotificationDeliveryWorker;
use Drupal\openintranet_notifications_test\Plugin\NotificationChannel\CountingChannel;
use Drupal\user\Entity\User;

/**
 * Tests quiet-hours deferral in the NotificationDeliveryWorker.
 *
 * @group openintranet_notifications
 */
final class QuietHoursDeliveryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'openintranet_notifications',
    'openintranet_notifications_test',
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
   * The worker under test.
   */
  private NotificationDeliveryWorker $worker;

  /**
   * The recipient user id.
   */
  private const UID = 42;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('user_notification_settings');
    $this->installEntitySchema('openintranet_notification');
    $this->installEntitySchema('openintranet_notif_delivery');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['openintranet_notifications']);

    User::create(['uid' => self::UID, 'name' => 'recipient', 'status' => 1])->save();

    $manager = $this->container->get('plugin.manager.queue_worker');
    $worker = $manager->createInstance('openintranet_notification_delivery');
    \assert($worker instanceof NotificationDeliveryWorker);
    $this->worker = $worker;
  }

  /**
   * Stores a quiet-hours window for the recipient, in UTC, around "now".
   *
   * Builds the window so the current request time falls inside it: start one
   * hour before now, end one hour after now (same-day, no midnight crossing).
   *
   * @return int
   *   The unix timestamp of the window end (07:00-style boundary in UTC).
   */
  private function setQuietHoursCoveringNow(): int {
    $now = \Drupal::time()->getRequestTime();
    $tz = new \DateTimeZone('UTC');
    $start = (new \DateTime('@' . ($now - 3600)))->setTimezone($tz)->format('H:i');
    $end = (new \DateTime('@' . ($now + 3600)))->setTimezone($tz)->format('H:i');

    UserNotificationSettings::create([
      'uid' => self::UID,
      'quiet_hours_start' => $start,
      'quiet_hours_end' => $end,
      'quiet_hours_tz' => 'UTC',
    ])->save();

    // Compute the expected end timestamp for the assertion (today's end at or
    // after now).
    $endDt = (new \DateTime('@' . $now))->setTimezone($tz);
    [$h, $m] = \array_map('intval', \explode(':', $end));
    $endDt->setTime($h, $m, 0);
    return $endDt->getTimestamp();
  }

  /**
   * Creates a delivery row (with its parent notification) for a channel.
   *
   * @param string $channel
   *   The channel plugin id.
   *
   * @return \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface
   *   The saved delivery.
   */
  private function createDelivery(string $channel): NotificationDeliveryInterface {
    $notification = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification')
      ->create([
        'type' => 'default',
        'uid' => self::UID,
        'subject' => 'Hi',
        'body' => 'Body',
        'status' => 'queued',
      ]);
    $notification->save();

    $delivery = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery')
      ->create([
        'notification_id' => $notification->id(),
        'recipient_type' => 'user',
        'recipient_id' => self::UID,
        'channel' => $channel,
        'address' => 'addr',
        'status' => 'pending',
        'idempotency_key' => 'k-' . $channel . '-' . $notification->id(),
      ]);
    $delivery->save();

    return $delivery;
  }

  /**
   * Reloads a delivery fresh from storage.
   */
  private function reload(NotificationDeliveryInterface $delivery): NotificationDeliveryInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('openintranet_notif_delivery');
    $storage->resetCache([$delivery->id()]);
    /** @var \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $reloaded */
    $reloaded = $storage->load($delivery->id());
    return $reloaded;
  }

  /**
   * The current CountingChannel send counter.
   */
  private function countingSends(): int {
    return (int) \Drupal::state()->get(CountingChannel::STATE_KEY, 0);
  }

  /**
   * A transport delivery inside quiet hours is deferred, not sent.
   */
  public function testTransportChannelDeferredDuringQuietHours(): void {
    $expectedEnd = $this->setQuietHoursCoveringNow();
    $delivery = $this->createDelivery('counting');

    $thrown = NULL;
    try {
      $this->worker->processItem(['delivery_id' => $delivery->id()]);
    }
    catch (DelayedRequeueException $e) {
      $thrown = $e;
    }

    self::assertInstanceOf(DelayedRequeueException::class, $thrown);
    self::assertGreaterThan(0, $thrown->getDelay());
    // Nothing was sent.
    self::assertSame(0, $this->countingSends());

    // The delivery stays pending with next_attempt set to the quiet window end.
    $reloaded = $this->reload($delivery);
    self::assertSame('pending', $reloaded->get('status')->value);
    self::assertGreaterThan(\Drupal::time()->getRequestTime(), (int) $reloaded->get('next_attempt')->value);
    self::assertSame($expectedEnd, (int) $reloaded->get('next_attempt')->value);
  }

  /**
   * The inbox channel is never deferred for quiet hours.
   */
  public function testInboxChannelNotDeferred(): void {
    $this->setQuietHoursCoveringNow();
    $delivery = $this->createDelivery('inbox');

    // No DelayedRequeueException; the on-site channel sends regardless.
    $this->worker->processItem(['delivery_id' => $delivery->id()]);

    self::assertSame('sent', $this->reload($delivery)->get('status')->value);
  }

  /**
   * The log_only channel is never deferred for quiet hours.
   */
  public function testLogOnlyChannelNotDeferred(): void {
    $this->setQuietHoursCoveringNow();
    $delivery = $this->createDelivery('log_only');

    $this->worker->processItem(['delivery_id' => $delivery->id()]);

    self::assertSame('sent', $this->reload($delivery)->get('status')->value);
  }

  /**
   * A transport delivery with no quiet hours configured sends normally.
   */
  public function testNoQuietHoursSendsNormally(): void {
    // No UserNotificationSettings entity for the recipient.
    $delivery = $this->createDelivery('counting');

    $this->worker->processItem(['delivery_id' => $delivery->id()]);

    self::assertSame(1, $this->countingSends());
    self::assertSame('sent', $this->reload($delivery)->get('status')->value);
  }

}
