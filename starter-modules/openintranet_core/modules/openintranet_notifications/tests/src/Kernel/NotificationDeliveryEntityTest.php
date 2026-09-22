<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Dto\DeliveryResult;
use Drupal\openintranet_notifications\Entity\NotificationDelivery;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;

/**
 * Tests CRUD and helpers of the openintranet_notif_delivery entity.
 *
 * @group openintranet_notifications
 */
final class NotificationDeliveryEntityTest extends KernelTestBase {

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
    $this->installEntitySchema('openintranet_notif_delivery');
  }

  /**
   * Defaults are applied; markSent / scheduleRetry / isTerminal behave.
   */
  public function testCreateDefaultsAndHelpers(): void {
    $delivery = NotificationDelivery::create([
      'notification_id' => 1,
      'recipient_type' => 'user',
      'recipient_id' => 42,
      'channel' => 'log_only',
      'address' => 'inbox:42',
      'status' => 'pending',
      'idempotency_key' => 'idem-1',
    ]);
    $delivery->save();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery');
    $storage->resetCache();
    /** @var \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $reloaded */
    $reloaded = $storage->load($delivery->id());

    self::assertInstanceOf(NotificationDeliveryInterface::class, $reloaded);
    self::assertSame(0, (int) $reloaded->get('attempt_count')->value);
    self::assertSame('pending', $reloaded->get('status')->value);
    self::assertFalse($reloaded->isTerminal());

    // markSent flips status, stamps sent, records the provider id.
    $reloaded->markSent('provider-1');
    self::assertSame('sent', $reloaded->get('status')->value);
    self::assertNotNull($reloaded->get('sent')->value);
    self::assertGreaterThan(0, (int) $reloaded->get('sent')->value);
    self::assertSame('provider-1', $reloaded->get('provider_message_id')->value);
    self::assertTrue($reloaded->isTerminal());
  }

  /**
   * Failing a delivery records the error from the DeliveryResult.
   */
  public function testMarkFailedRecordsError(): void {
    $delivery = NotificationDelivery::create([
      'notification_id' => 1,
      'recipient_type' => 'user',
      'recipient_id' => 42,
      'channel' => 'log_only',
      'status' => 'pending',
      'idempotency_key' => 'idem-2',
    ]);
    $delivery->markFailed(DeliveryResult::permanentFailure('HTTP_400', 'bad request'));

    self::assertSame('failed', $delivery->get('status')->value);
    self::assertSame('HTTP_400', $delivery->get('last_error_code')->value);
    self::assertSame('bad request', $delivery->get('last_error_message')->value);
    self::assertFalse($delivery->isTerminal());
  }

  /**
   * Scheduling a retry sets next_attempt ahead and keeps status pending.
   */
  public function testScheduleRetryKeepsPending(): void {
    $delivery = NotificationDelivery::create([
      'notification_id' => 1,
      'recipient_type' => 'user',
      'recipient_id' => 42,
      'channel' => 'log_only',
      'status' => 'pending',
      'idempotency_key' => 'idem-3',
    ]);
    $now = \Drupal::time()->getRequestTime();
    $delivery->scheduleRetry(300);

    self::assertSame('pending', $delivery->get('status')->value);
    self::assertGreaterThan($now, (int) $delivery->get('next_attempt')->value);
  }

}
