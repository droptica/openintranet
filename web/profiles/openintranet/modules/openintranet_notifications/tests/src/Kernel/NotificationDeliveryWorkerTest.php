<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Drupal\openintranet_notifications\Plugin\QueueWorker\NotificationDeliveryWorker;
use Drupal\openintranet_notifications_test\Plugin\NotificationChannel\CountingChannel;
use Drupal\user\Entity\User;

/**
 * Tests the idempotent NotificationDeliveryWorker (retry + backoff + claim).
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
   * The first-retry backoff in seconds (BACKOFF_BASE_SECONDS * 1).
   */
  private const FIRST_BACKOFF = 300;

  /**
   * The key-value collection holding in-flight send claims.
   */
  private const CLAIM_COLLECTION = 'openintranet_notifications.delivery_claim';

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
   * The current CountingChannel send counter.
   */
  private function countingSends(): int {
    return (int) \Drupal::state()->get(CountingChannel::STATE_KEY, 0);
  }

  /**
   * A terminal (already-sent) delivery is never re-sent.
   */
  public function testIdempotencyGuardSkipsAlreadySentDelivery(): void {
    $delivery = $this->createDelivery(['channel' => 'counting', 'status' => 'sent']);

    $this->worker->processItem(['delivery_id' => $delivery->id()]);

    self::assertSame(0, $this->countingSends());
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
   * A retryable failure bumps the attempt count and DELAYS the same item.
   *
   * The worker must NOT enqueue a zero-delay copy: it persists the bumped
   * attempt/backoff then throws DelayedRequeueException so cron delays the same
   * item (FIX #1). On the first retry the delay is exactly BACKOFF_BASE_SECONDS
   * (FIX #14).
   */
  public function testRetryableFailureBumpsAttemptAndThrowsDelayedRequeue(): void {
    $delivery = $this->createDelivery(['channel' => 'flaky_retryable', 'status' => 'pending']);
    $requestTime = \Drupal::time()->getRequestTime();

    $thrown = NULL;
    try {
      $this->worker->processItem(['delivery_id' => $delivery->id()]);
    }
    catch (DelayedRequeueException $e) {
      $thrown = $e;
    }

    self::assertInstanceOf(DelayedRequeueException::class, $thrown);
    self::assertSame(self::FIRST_BACKOFF, $thrown->getDelay());

    $reloaded = $this->reload($delivery);
    self::assertSame(1, (int) $reloaded->get('attempt_count')->value);
    self::assertSame('pending', $reloaded->get('status')->value);
    self::assertSame($requestTime + self::FIRST_BACKOFF, (int) $reloaded->get('next_attempt')->value);
  }

  /**
   * An early-defer (next_attempt in the future) delays without sending.
   *
   * FIX #16: the worker throws DelayedRequeueException and the channel is never
   * called, so the counting state counter stays 0.
   */
  public function testEarlyDeferThrowsDelayedRequeueWithoutSending(): void {
    $requestTime = \Drupal::time()->getRequestTime();
    $delivery = $this->createDelivery([
      'channel' => 'counting',
      'status' => 'pending',
      'next_attempt' => $requestTime + 9999,
    ]);

    $thrown = NULL;
    try {
      $this->worker->processItem(['delivery_id' => $delivery->id()]);
    }
    catch (DelayedRequeueException $e) {
      $thrown = $e;
    }

    self::assertInstanceOf(DelayedRequeueException::class, $thrown);
    self::assertGreaterThan(0, $thrown->getDelay());
    self::assertSame(0, $this->countingSends());
  }

  /**
   * Below the attempt budget, a retryable failure delays the item (FIX #15).
   */
  public function testRetryBelowMaxAttemptsThrowsDelayedRequeue(): void {
    $delivery = $this->createDelivery([
      'channel' => 'flaky_retryable',
      'status' => 'pending',
      'attempt_count' => 3,
    ]);

    $thrown = NULL;
    try {
      $this->worker->processItem(['delivery_id' => $delivery->id()]);
    }
    catch (DelayedRequeueException $e) {
      $thrown = $e;
    }

    self::assertInstanceOf(DelayedRequeueException::class, $thrown);
    // attempt_count becomes 4; 4 < 5 so it retries with delay 300 * 4.
    self::assertSame(self::FIRST_BACKOFF * 4, $thrown->getDelay());

    $reloaded = $this->reload($delivery);
    self::assertSame('pending', $reloaded->get('status')->value);
    self::assertSame(4, (int) $reloaded->get('attempt_count')->value);
  }

  /**
   * Exhausting the attempt budget turns a retryable failure permanent.
   *
   * FIX #15: no exception is thrown, the row is 'failed' and carries the
   * channel's retryable error code.
   */
  public function testMaxAttemptsExhaustedBecomesPermanent(): void {
    $delivery = $this->createDelivery([
      'channel' => 'flaky_retryable',
      'status' => 'pending',
      'attempt_count' => 5,
    ]);

    $this->worker->processItem(['delivery_id' => $delivery->id()]);

    $reloaded = $this->reload($delivery);
    self::assertSame('failed', $reloaded->get('status')->value);
    self::assertSame('E_RETRY', $reloaded->get('last_error_code')->value);
  }

  /**
   * A permanent failure marks the delivery failed with no exception (FIX #15).
   */
  public function testPermanentFailureMarksFailed(): void {
    $delivery = $this->createDelivery(['channel' => 'always_permanent', 'status' => 'pending']);

    $this->worker->processItem(['delivery_id' => $delivery->id()]);

    $reloaded = $this->reload($delivery);
    self::assertSame('failed', $reloaded->get('status')->value);
    self::assertSame('E_PERM', $reloaded->get('last_error_code')->value);
  }

  /**
   * Processing the same sent delivery twice never sends twice (FIX #11).
   */
  public function testProcessTwiceIsIdempotent(): void {
    $delivery = $this->createDelivery(['channel' => 'counting', 'status' => 'pending']);

    $this->worker->processItem(['delivery_id' => $delivery->id()]);
    self::assertSame(1, $this->countingSends());
    self::assertSame('sent', $this->reload($delivery)->get('status')->value);

    // A second pass over the now-terminal row must not send again.
    $this->worker->processItem(['delivery_id' => $delivery->id()]);
    self::assertSame(1, $this->countingSends());
    self::assertSame('sent', $this->reload($delivery)->get('status')->value);
  }

  /**
   * A pre-existing claim blocks a concurrent send (FIX #2).
   */
  public function testConcurrentClaimSkipsSend(): void {
    $delivery = $this->createDelivery(['channel' => 'counting', 'status' => 'pending']);
    $claimKey = (string) $delivery->get('idempotency_key')->value;

    // Simulate another worker already holding the claim for this delivery.
    /** @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $factory */
    $factory = $this->container->get('keyvalue.expirable');
    $factory->get(self::CLAIM_COLLECTION)
      ->setWithExpire($claimKey, \Drupal::time()->getRequestTime(), 120);

    $this->worker->processItem(['delivery_id' => $delivery->id()]);

    self::assertSame(0, $this->countingSends());
    self::assertSame('pending', $this->reload($delivery)->get('status')->value);
  }

  /**
   * A delivery on a missing channel is skipped, not sent (FIX #16).
   */
  public function testMissingChannelIsSkipped(): void {
    $delivery = $this->createDelivery(['channel' => 'does_not_exist', 'status' => 'pending']);

    $this->worker->processItem(['delivery_id' => $delivery->id()]);

    self::assertSame('skipped', $this->reload($delivery)->get('status')->value);
    self::assertSame(0, $this->countingSends());
  }

  /**
   * A delivery on an unavailable channel is skipped, not sent (FIX #16).
   */
  public function testUnavailableChannelIsSkipped(): void {
    $delivery = $this->createDelivery(['channel' => 'unavailable_stub', 'status' => 'pending']);

    $this->worker->processItem(['delivery_id' => $delivery->id()]);

    self::assertSame('skipped', $this->reload($delivery)->get('status')->value);
  }

}
