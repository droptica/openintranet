<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Drush;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Drush\Commands\NotificationCommands;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Drush\Drush;
use Drush\Log\DrushLoggerManager;

/**
 * Tests the NotificationCommands drush command status/limit logic.
 *
 * @group openintranet_notifications
 */
final class NotificationCommandsTest extends KernelTestBase {

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
   * The command under test.
   */
  private NotificationCommands $commands;

  /**
   * The delivery storage.
   */
  private EntityStorageInterface $deliveryStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // The command formats its success line with Drush's global dt(); load the
    // include that defines it (no Drush bootstrap runs in a kernel test).
    if (!function_exists('dt')) {
      $drushDir = dirname((new \ReflectionClass(Drush::class))->getFileName(), 2);
      require_once $drushDir . '/includes/output.inc';
    }
    $this->installEntitySchema('user');
    $this->installEntitySchema('openintranet_notification');
    $this->installEntitySchema('openintranet_notif_delivery');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['openintranet_notifications']);

    $this->commands = NotificationCommands::create($this->container);
    // The command logs a success line; outside a Drush bootstrap there is no
    // logger, so give it a manager with no attached output (a safe no-op).
    $this->commands->setLogger(new DrushLoggerManager());
    $this->deliveryStorage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery');
  }

  /**
   * Creates a delivery row with the given status.
   */
  private function createDelivery(string $status): int {
    /** @var \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $delivery */
    $delivery = $this->deliveryStorage->create([
      'notification_id' => 1,
      'channel' => 'inbox',
      'status' => $status,
      'address' => 'inbox:1',
      'attempt_count' => 5,
      'next_attempt' => 9999,
    ]);
    $delivery->save();
    return (int) $delivery->id();
  }

  /**
   * The status of a stored delivery, reloaded from storage.
   */
  private function statusOf(int $id): string {
    $this->deliveryStorage->resetCache([$id]);
    $delivery = $this->deliveryStorage->load($id);
    \assert($delivery instanceof NotificationDeliveryInterface);
    return (string) $delivery->get('status')->value;
  }

  /**
   * Re-queues only failed deliveries, leaving sent and pending untouched.
   */
  public function testRetryFailedRequeuesOnlyFailed(): void {
    $failedA = $this->createDelivery('failed');
    $failedB = $this->createDelivery('failed');
    $sent = $this->createDelivery('sent');
    $pending = $this->createDelivery('pending');

    $this->commands->retryFailed([]);

    // Both failed rows are reset to pending and enqueued.
    self::assertSame('pending', $this->statusOf($failedA));
    self::assertSame('pending', $this->statusOf($failedB));
    self::assertSame(2, \Drupal::queue('openintranet_notification_delivery')->numberOfItems());

    // The sent row is left untouched; the already-pending row is not requeued.
    self::assertSame('sent', $this->statusOf($sent));
    self::assertSame('pending', $this->statusOf($pending));
  }

  /**
   * The limit caps how many failed deliveries a single run re-queues.
   */
  public function testRetryFailedRespectsLimit(): void {
    $this->createDelivery('failed');
    $this->createDelivery('failed');
    $this->createDelivery('failed');

    $this->commands->retryFailed(['limit' => 1]);

    self::assertSame(1, \Drupal::queue('openintranet_notification_delivery')->numberOfItems());

    $pending = 0;
    foreach ($this->deliveryStorage->loadMultiple() as $delivery) {
      \assert($delivery instanceof NotificationDeliveryInterface);
      if ((string) $delivery->get('status')->value === 'pending') {
        $pending++;
      }
    }
    self::assertSame(1, $pending);
  }

}
