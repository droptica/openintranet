<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Drupal\openintranet_notifications\Plugin\QueueWorker\NotificationDeliveryWorker;
use Drupal\openintranet_notifications_test\Plugin\NotificationChannel\CountingChannel;
use Drupal\user\Entity\User;

/**
 * Tests the idempotent NotificationDeliveryWorker (retry + backoff).
 *
 * @group openintranet_notifications
 */
final class NotificationDeliveryWorkerTest extends KernelTestBase {

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
   * The recipient user.
   */
  private User $recipient;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('openintranet_notification');
    $this->installEntitySchema('openintranet_notif_delivery');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['openintranet_notifications']);

    $this->recipient = User::create(['uid' => 42, 'name' => 'recipient', 'status' => 1]);
    $this->recipient->save();

    // Instantiate the worker via its plugin manager so DI is exercised.
    $manager = $this->container->get('plugin.manager.queue_worker');
    $worker = $manager->createInstance('openintranet_notification_delivery');
    \assert($worker instanceof NotificationDeliveryWorker);
    $this->worker = $worker;
  }

  /**
   * Creates a delivery row (with its parent notification) for a channel.
   *
   * @param array<string, mixed> $values
   *   Overrides for the delivery row (channel, status, attempt_count, …).
   *
   * @return \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface
   *   The saved delivery.
   */
  private function createDelivery(array $values): NotificationDeliveryInterface {
    $notification = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification')
      ->create([
        'type' => 'default',
        'uid' => 42,
        'subject' => 'Hi',
        'body' => 'Body',
        'status' => 'queued',
      ]);
    $notification->save();

    $delivery = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery')
      ->create($values + [
        'notification_id' => $notification->id(),
        'recipient_type' => 'user',
        'recipient_id' => 42,
        'address' => 'addr',
        'status' => 'pending',
        'idempotency_key' => 'k-' . $notification->id(),
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
   * Number of items waiting in the delivery queue.
   */
  private function queueCount(): int {
    return \Drupal::queue('openintranet_notification_delivery')->numberOfItems();
  }

  /**
   * A terminal (already-sent) delivery is never re-sent.
   */
  public function testIdempotencyGuardSkipsAlreadySentDelivery(): void {
    $delivery = $this->createDelivery(['channel' => 'counting', 'status' => 'sent']);

    $this->worker->processItem(['delivery_id' => $delivery->id()]);

    self::assertSame(0, (int) \Drupal::state()->get(CountingChannel::STATE_KEY, 0));
  }

  /**
   * A successful send marks the delivery sent.
   */
  public function testSuccessfulSendMarksSent(): void {
    $delivery = $this->createDelivery(['channel' => 'null', 'status' => 'pending']);

    $this->worker->processItem(['delivery_id' => $delivery->id()]);

    self::assertSame('sent', $this->reload($delivery)->get('status')->value);
  }

  /**
   * A retryable failure bumps the attempt count and re-enqueues with backoff.
   */
  public function testRetryableFailureBumpsAttemptAndReEnqueuesWithBackoff(): void {
    $delivery = $this->createDelivery(['channel' => 'flaky_retryable', 'status' => 'pending']);

    $this->worker->processItem(['delivery_id' => $delivery->id()]);

    $reloaded = $this->reload($delivery);
    self::assertSame(1, (int) $reloaded->get('attempt_count')->value);
    self::assertSame('pending', $reloaded->get('status')->value);
    self::assertGreaterThan(\Drupal::time()->getRequestTime(), (int) $reloaded->get('next_attempt')->value);
    self::assertSame(1, $this->queueCount());
  }

  /**
   * A permanent failure marks the delivery failed and does not re-enqueue.
   */
  public function testPermanentFailureMarksFailedAndDoesNotReEnqueue(): void {
    $delivery = $this->createDelivery(['channel' => 'always_permanent', 'status' => 'pending']);

    $this->worker->processItem(['delivery_id' => $delivery->id()]);

    self::assertSame('failed', $this->reload($delivery)->get('status')->value);
    self::assertSame(0, $this->queueCount());
  }

  /**
   * Exhausting the attempt budget turns a retryable failure permanent.
   */
  public function testMaxAttemptsExhaustedBecomesPermanent(): void {
    $delivery = $this->createDelivery([
      'channel' => 'flaky_retryable',
      'status' => 'pending',
      'attempt_count' => 5,
    ]);

    $this->worker->processItem(['delivery_id' => $delivery->id()]);

    self::assertSame('failed', $this->reload($delivery)->get('status')->value);
    self::assertSame(0, $this->queueCount());
  }

}
