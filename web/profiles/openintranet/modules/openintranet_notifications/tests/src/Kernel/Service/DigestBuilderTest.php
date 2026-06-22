<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Event\NotificationDigestReadyEvent;
use Drupal\openintranet_notifications\Event\NotificationEvents;
use Drupal\openintranet_notifications\Service\DigestBuilder;

/**
 * Tests the DigestBuilder service.
 *
 * @group openintranet_notifications
 */
final class DigestBuilderTest extends KernelTestBase {

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
   * The builder under test.
   */
  private DigestBuilder $builder;

  /**
   * The notification storage.
   */
  private EntityStorageInterface $notificationStorage;

  /**
   * The uids captured from DIGEST_READY events fired during a run.
   *
   * @var int[]
   */
  private array $firedUids = [];

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

    // Drop the shipped types so only our fixtures match the policy query.
    $typeStorage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification_type');
    $typeStorage->delete($typeStorage->loadMultiple());

    // A digest_only type and an immediate (user_preferences) type.
    NotificationType::create([
      'id' => 'digest',
      'label' => 'Digest type',
      'delivery_policy' => 'digest_only',
    ])->save();
    NotificationType::create([
      'id' => 'immediate',
      'label' => 'Immediate type',
      'delivery_policy' => 'user_preferences',
    ])->save();

    $this->builder = $this->container->get('openintranet_notifications.digest_builder');
    $this->notificationStorage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification');

    // Capture the recipient uid of every DIGEST_READY event.
    $this->container->get('event_dispatcher')->addListener(
      NotificationEvents::DIGEST_READY,
      function (NotificationDigestReadyEvent $event): void {
        $this->firedUids[] = $event->uid;
      },
    );
  }

  /**
   * Creates a notification of the given type for a recipient.
   */
  private function createNotification(string $type, int $uid): int {
    $notification = $this->notificationStorage->create([
      'type' => $type,
      'uid' => $uid,
      'subject' => $type . ':' . $uid,
      'status' => 'created',
      'priority' => 'normal',
    ]);
    $notification->save();
    return (int) $notification->id();
  }

  /**
   * Fires DIGEST_READY once per user, marks digested, and skips immediate ones.
   */
  public function testBuildAndDispatchGroupsPerUser(): void {
    // User A: two digest_only items; user B: one. Plus an immediate item for A.
    $a1 = $this->createNotification('digest', 1);
    $a2 = $this->createNotification('digest', 1);
    $b1 = $this->createNotification('digest', 2);
    $immediate = $this->createNotification('immediate', 1);

    $count = $this->builder->buildAndDispatch();

    // Two users were digested.
    self::assertSame(2, $count);

    // DIGEST_READY fired exactly once per user.
    sort($this->firedUids);
    self::assertSame([1, 2], $this->firedUids);

    $this->notificationStorage->resetCache();
    // The three digest_only notifications are now digested.
    foreach ([$a1, $a2, $b1] as $id) {
      $notification = $this->notificationStorage->load($id);
      \assert($notification instanceof NotificationInterface);
      self::assertTrue($notification->isDigested());
    }
    // The immediate one is untouched and never fired.
    $notification = $this->notificationStorage->load($immediate);
    \assert($notification instanceof NotificationInterface);
    self::assertFalse($notification->isDigested());
  }

  /**
   * Re-running after a digest fires nothing and returns 0 (idempotency).
   */
  public function testRerunIsIdempotent(): void {
    $this->createNotification('digest', 1);
    $this->createNotification('digest', 2);

    self::assertSame(2, $this->builder->buildAndDispatch());

    // Reset the capture and re-run: everything is already digested.
    $this->firedUids = [];
    $count = $this->builder->buildAndDispatch();

    self::assertSame(0, $count);
    self::assertSame([], $this->firedUids);
  }

  /**
   * The limit caps how many candidate rows a single run considers.
   */
  public function testLimitCapsCandidates(): void {
    // Three pending items across two users; a limit of 1 candidate row picks
    // only the oldest (created ASC), so exactly one user is digested.
    $this->createNotification('digest', 1);
    $this->createNotification('digest', 1);
    $this->createNotification('digest', 2);

    $count = $this->builder->buildAndDispatch(1);

    self::assertSame(1, $count);
    self::assertSame([1], $this->firedUids);
  }

  /**
   * Cron builds and dispatches digests after the retention purge.
   */
  public function testCronDispatchesDigests(): void {
    $this->createNotification('digest', 1);

    \Drupal::moduleHandler()->invoke('openintranet_notifications', 'cron');

    self::assertSame([1], $this->firedUids);
  }

}
