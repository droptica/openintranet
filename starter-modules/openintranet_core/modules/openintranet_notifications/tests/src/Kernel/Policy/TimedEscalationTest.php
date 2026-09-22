<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Policy;

use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Plugin\QueueWorker\NotificationDeliveryWorker;
use Drupal\openintranet_notifications_test\Plugin\NotificationChannel\EscalationImmediateChannel;
use Drupal\openintranet_notifications_test\Plugin\NotificationChannel\SmsSmsapiStubChannel;
use Drupal\user\Entity\User;

/**
 * Tests timed channel escalation for urgent notifications (00-synteza §3.2).
 *
 * The immediate tier (escalation_immediate) fires at once; the escalation tier
 * (sms_smsapi) is held ESCALATION_DELAY_SECONDS behind it via next_attempt. A
 * successful immediate send cancels the not-yet-due SMS; a permanent immediate
 * failure leaves the SMS to fire when its delay elapses. normal priority tiers
 * nothing.
 *
 * @group openintranet_notifications
 */
final class TimedEscalationTest extends KernelTestBase {

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
   * The escalation delay UrgentEscalationPolicy applies (default 600).
   */
  private const ESCALATION_DELAY = 600;

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

    $typeStorage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification_type');
    $typeStorage->delete($typeStorage->loadMultiple());

    $this->config('openintranet_notifications.settings')
      ->set('enabled_channels', ['escalation_immediate', 'sms_smsapi'])
      ->save();

    User::create(['uid' => 50, 'name' => 'recipient', 'status' => 1])->save();
  }

  /**
   * Creates an escalating-policy type with the given default priority.
   */
  private function createType(string $id, string $priority): NotificationType {
    $type = NotificationType::create([
      'id' => $id,
      'label' => ucfirst($id),
      'default_priority' => $priority,
      'default_channels' => ['escalation_immediate', 'sms_smsapi'],
      'forced_channels' => ['escalation_immediate', 'sms_smsapi'],
      'delivery_policy' => 'urgent_escalation',
      'dedupe_window' => 0,
    ]);
    $type->save();
    return $type;
  }

  /**
   * Dispatches one urgent notification to user 50, returns its delivery rows.
   *
   * @return array<string, \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface>
   *   The delivery rows keyed by channel id.
   */
  private function dispatchUrgent(string $typeId): array {
    $factory = $this->container->get('openintranet_notifications.notification_factory');
    $dispatcher = $this->container->get('openintranet_notifications.notification_dispatcher');
    $n = $factory->create($typeId, ['uid' => 50, 'subject' => 'Hi', 'body' => 'B']);
    $n->save();
    $dispatcher->enqueue($n);

    $storage = $this->container->get('entity_type.manager')->getStorage('openintranet_notif_delivery');
    $rows = [];
    foreach ($storage->loadByProperties(['notification_id' => $n->id()]) as $delivery) {
      \assert($delivery instanceof NotificationDeliveryInterface);
      $rows[(string) $delivery->get('channel')->value] = $delivery;
    }
    return $rows;
  }

  /**
   * Runs the worker over one delivery row.
   */
  private function process(NotificationDeliveryInterface $delivery): void {
    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance('openintranet_notification_delivery');
    \assert($worker instanceof NotificationDeliveryWorker);
    try {
      $worker->processItem(['delivery_id' => $delivery->id()]);
    }
    catch (DelayedRequeueException) {
      // A retryable/not-due row re-queues with a delay; that is expected.
    }
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
   * Urgent: 2 rows; immediate is due now, sms is held ~now + 600.
   */
  public function testUrgentDelaysEscalationTier(): void {
    $now = \Drupal::time()->getRequestTime();
    $rows = $this->dispatchUrgent($this->createType('urgent_type', 'urgent')->id());

    self::assertCount(2, $rows);
    self::assertArrayHasKey('escalation_immediate', $rows);
    self::assertArrayHasKey('sms_smsapi', $rows);

    self::assertSame(0, (int) $rows['escalation_immediate']->get('next_attempt')->value);
    self::assertSame($now + self::ESCALATION_DELAY, (int) $rows['sms_smsapi']->get('next_attempt')->value);
  }

  /**
   * A successful immediate send cancels the not-yet-due SMS tier.
   */
  public function testImmediateSuccessCancelsPendingSms(): void {
    \Drupal::state()->set(EscalationImmediateChannel::STATE_KEY, 'success');
    $rows = $this->dispatchUrgent($this->createType('urgent_type', 'urgent')->id());

    $this->process($rows['escalation_immediate']);

    self::assertSame('sent', $this->reload($rows['escalation_immediate'])->get('status')->value);
    self::assertSame('cancelled', $this->reload($rows['sms_smsapi'])->get('status')->value);
  }

  /**
   * A permanent immediate failure leaves the SMS to fire when due.
   */
  public function testImmediatePermanentFailureLeavesSmsDue(): void {
    \Drupal::state()->set(EscalationImmediateChannel::STATE_KEY, 'permanent');
    $now = \Drupal::time()->getRequestTime();
    $rows = $this->dispatchUrgent($this->createType('urgent_type', 'urgent')->id());

    $this->process($rows['escalation_immediate']);

    self::assertSame('failed', $this->reload($rows['escalation_immediate'])->get('status')->value);
    // The SMS tier survives: still pending and still due after its delay.
    $sms = $this->reload($rows['sms_smsapi']);
    self::assertSame('pending', $sms->get('status')->value);
    self::assertSame($now + self::ESCALATION_DELAY, (int) $sms->get('next_attempt')->value);
  }

  /**
   * After its delay elapses, the surviving SMS tier actually sends.
   */
  public function testSurvivingSmsSendsWhenDue(): void {
    \Drupal::state()->set(EscalationImmediateChannel::STATE_KEY, 'permanent');
    \Drupal::state()->set(SmsSmsapiStubChannel::STATE_KEY, 'success');
    $rows = $this->dispatchUrgent($this->createType('urgent_type', 'urgent')->id());
    $this->process($rows['escalation_immediate']);

    // Make the SMS row due (its next_attempt was now + 600).
    $sms = $this->reload($rows['sms_smsapi']);
    $sms->set('next_attempt', \Drupal::time()->getRequestTime())->save();

    $this->process($sms);

    self::assertSame('sent', $this->reload($rows['sms_smsapi'])->get('status')->value);
  }

  /**
   * Normal priority tiers nothing: both rows are immediate, no cancellation.
   */
  public function testNormalPriorityHasNoDelaysOrCancellation(): void {
    \Drupal::state()->set(EscalationImmediateChannel::STATE_KEY, 'success');
    $rows = $this->dispatchUrgent($this->createType('normal_type', 'normal')->id());

    self::assertCount(2, $rows);
    self::assertSame(0, (int) $rows['escalation_immediate']->get('next_attempt')->value);
    self::assertSame(0, (int) $rows['sms_smsapi']->get('next_attempt')->value);

    // The immediate send succeeds but the SMS is due now (not a held tier), so
    // it is NOT cancelled by cancel-on-success.
    $this->process($rows['escalation_immediate']);
    self::assertSame('sent', $this->reload($rows['escalation_immediate'])->get('status')->value);
    self::assertSame('pending', $this->reload($rows['sms_smsapi'])->get('status')->value);
  }

}
