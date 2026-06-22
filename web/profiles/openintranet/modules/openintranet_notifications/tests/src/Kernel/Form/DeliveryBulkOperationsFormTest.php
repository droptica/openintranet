<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Form\DeliveryBulkOperationsForm;
use Drupal\user\Entity\User;

/**
 * Tests the bulk retry/cancel deliveries form (Chunk 4C).
 *
 * @group openintranet_notifications
 *
 * @coversDefaultClass \Drupal\openintranet_notifications\Form\DeliveryBulkOperationsForm
 */
final class DeliveryBulkOperationsFormTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('openintranet_notification');
    $this->installEntitySchema('openintranet_notif_delivery');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['openintranet_notifications']);
  }

  /**
   * Builds a saved delivery row in the given status.
   *
   * @param string $status
   *   The delivery status.
   * @param int $attemptCount
   *   The attempt count to stamp.
   *
   * @return \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface
   *   The saved delivery.
   */
  private function createDelivery(string $status, int $attemptCount = 0): object {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery');
    /** @var \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $delivery */
    $delivery = $storage->create([
      'notification_id' => 1,
      'channel' => 'inbox',
      'status' => $status,
      'attempt_count' => $attemptCount,
      'next_attempt' => 1234,
      'address' => 'inbox:1',
    ]);
    $delivery->save();
    return $delivery;
  }

  /**
   * Submits the bulk form programmatically.
   *
   * @param array<int|string, int|string> $selected
   *   The selected delivery ids keyed by id (tableselect shape).
   * @param string $op
   *   The op value identifying the pressed button ('retry' or 'cancel').
   *
   * @return \Drupal\Core\Form\FormState
   *   The submitted form state.
   */
  private function submit(array $selected, string $op): FormState {
    $form_object = DeliveryBulkOperationsForm::create($this->container);
    $form_state = new FormState();
    $form_state->setValues([
      'deliveries' => $selected,
      'op' => $op,
    ]);
    $form_state->setSubmitHandlers([[$form_object, $op === 'retry' ? 'retrySubmit' : 'cancelSubmit']]);
    $this->container->get('form_builder')->submitForm($form_object, $form_state);
    return $form_state;
  }

  /**
   * Reloads a delivery's status fresh from storage.
   */
  private function statusOf(int $id): string {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery');
    $storage->resetCache([$id]);
    /** @var \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $delivery */
    $delivery = $storage->load($id);
    return (string) $delivery->get('status')->value;
  }

  /**
   * Retry requeues failed deliveries and leaves terminal ones untouched.
   *
   * @covers ::retrySubmit
   */
  public function testRetryRequeuesOnlyFailedSelected(): void {
    $failed = $this->createDelivery('failed', 3);
    $sent = $this->createDelivery('sent', 1);
    $pending = $this->createDelivery('pending', 0);

    $selected = [
      (int) $failed->id() => (int) $failed->id(),
      (int) $sent->id() => (int) $sent->id(),
      (int) $pending->id() => (int) $pending->id(),
    ];
    $this->submit($selected, 'retry');

    // The failed delivery is reset to pending and re-queued.
    self::assertSame('pending', $this->statusOf((int) $failed->id()));
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery');
    $storage->resetCache([(int) $failed->id()]);
    /** @var \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $reloaded */
    $reloaded = $storage->load((int) $failed->id());
    self::assertSame(0, (int) $reloaded->get('attempt_count')->value);
    self::assertSame(0, (int) $reloaded->get('next_attempt')->value);

    // The terminal 'sent' delivery is NOT retried by this form.
    self::assertSame('sent', $this->statusOf((int) $sent->id()));
    // A pending delivery is not "failed", so retry leaves it alone.
    self::assertSame('pending', $this->statusOf((int) $pending->id()));

    // Exactly one queue item: the single failed delivery.
    self::assertSame(1, (int) \Drupal::queue('openintranet_notification_delivery')->numberOfItems());
  }

  /**
   * Cancel sets pending/processing selected deliveries to cancelled.
   *
   * @covers ::cancelSubmit
   */
  public function testCancelCancelsPendingAndProcessingSelected(): void {
    $pending = $this->createDelivery('pending');
    $processing = $this->createDelivery('processing');
    $sent = $this->createDelivery('sent');
    $failed = $this->createDelivery('failed');

    $selected = [
      (int) $pending->id() => (int) $pending->id(),
      (int) $processing->id() => (int) $processing->id(),
      (int) $sent->id() => (int) $sent->id(),
      (int) $failed->id() => (int) $failed->id(),
    ];
    $this->submit($selected, 'cancel');

    self::assertSame('cancelled', $this->statusOf((int) $pending->id()));
    self::assertSame('cancelled', $this->statusOf((int) $processing->id()));
    // A terminal sent delivery is left untouched.
    self::assertSame('sent', $this->statusOf((int) $sent->id()));
    // A failed delivery is not pending/processing, so cancel skips it.
    self::assertSame('failed', $this->statusOf((int) $failed->id()));

    // Cancel enqueues nothing.
    self::assertSame(0, (int) \Drupal::queue('openintranet_notification_delivery')->numberOfItems());
  }

  /**
   * Unselected deliveries are never touched by either operation.
   *
   * @covers ::retrySubmit
   */
  public function testUnselectedFailedIsNotRequeued(): void {
    User::create(['uid' => 1, 'name' => 'admin', 'status' => 1])->save();
    $selected = $this->createDelivery('failed');
    $unselected = $this->createDelivery('failed');

    $this->submit([(int) $selected->id() => (int) $selected->id()], 'retry');

    self::assertSame('pending', $this->statusOf((int) $selected->id()));
    self::assertSame('failed', $this->statusOf((int) $unselected->id()));
    self::assertSame(1, (int) \Drupal::queue('openintranet_notification_delivery')->numberOfItems());
  }

}
