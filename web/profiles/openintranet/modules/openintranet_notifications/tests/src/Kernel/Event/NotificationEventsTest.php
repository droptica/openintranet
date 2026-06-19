<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Event;

use Drupal\eca\Event\EntityEventInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\Notification;
use Drupal\openintranet_notifications\Entity\NotificationDelivery;
use Drupal\openintranet_notifications\Event\NotificationCreatedEvent;
use Drupal\openintranet_notifications\Event\NotificationDeliveredEvent;
use Drupal\openintranet_notifications\Event\NotificationDigestReadyEvent;
use Drupal\openintranet_notifications\Event\NotificationEvents;
use Drupal\openintranet_notifications\Event\NotificationFailedEvent;
use Drupal\openintranet_notifications\Event\NotificationPermanentlyFailedEvent;
use Drupal\openintranet_notifications\Event\NotificationQueuedEvent;
use Drupal\openintranet_notifications\Event\NotificationSeenEvent;
use Drupal\openintranet_notifications\Plugin\ECA\Event\NotificationEvent;

/**
 * Tests the seven first-class ECA notification events.
 *
 * @group openintranet_notifications
 */
final class NotificationEventsTest extends KernelTestBase {

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
    $this->installEntitySchema('openintranet_notification');
    $this->installEntitySchema('openintranet_notif_delivery');
  }

  /**
   * The ECA event plugin manager discovers all seven notification derivatives.
   */
  public function testAllDerivativesAreDiscovered(): void {
    /** @var \Drupal\Component\Plugin\PluginManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.eca.event');
    $definitions = $manager->getDefinitions();

    $expected = [
      'notification:created',
      'notification:queued',
      'notification:delivered',
      'notification:failed',
      'notification:permanently_failed',
      'notification:seen',
      'notification:digest_ready',
    ];
    foreach ($expected as $id) {
      self::assertArrayHasKey($id, $definitions, "Derivative $id is discovered.");
    }
  }

  /**
   * Each derivative's event_name equals its NotificationEvents constant.
   */
  public function testDefinitionsMapToEventConstants(): void {
    $definitions = NotificationEvent::definitions();

    self::assertSame(
      [
        'created',
        'queued',
        'delivered',
        'failed',
        'permanently_failed',
        'seen',
        'digest_ready',
      ],
      array_keys($definitions),
    );

    self::assertSame(NotificationEvents::CREATED, $definitions['created']['event_name']);
    self::assertSame(NotificationEvents::QUEUED, $definitions['queued']['event_name']);
    self::assertSame(NotificationEvents::DELIVERED, $definitions['delivered']['event_name']);
    self::assertSame(NotificationEvents::FAILED, $definitions['failed']['event_name']);
    self::assertSame(NotificationEvents::PERMANENTLY_FAILED, $definitions['permanently_failed']['event_name']);
    self::assertSame(NotificationEvents::SEEN, $definitions['seen']['event_name']);
    self::assertSame(NotificationEvents::DIGEST_READY, $definitions['digest_ready']['event_name']);

    // Each definition maps to a concrete event class.
    self::assertSame(NotificationCreatedEvent::class, $definitions['created']['event_class']);
    self::assertSame(NotificationQueuedEvent::class, $definitions['queued']['event_class']);
    self::assertSame(NotificationDeliveredEvent::class, $definitions['delivered']['event_class']);
    self::assertSame(NotificationFailedEvent::class, $definitions['failed']['event_class']);
    self::assertSame(NotificationPermanentlyFailedEvent::class, $definitions['permanently_failed']['event_class']);
    self::assertSame(NotificationSeenEvent::class, $definitions['seen']['event_class']);
    self::assertSame(NotificationDigestReadyEvent::class, $definitions['digest_ready']['event_class']);
  }

  /**
   * The event-name constants carry the documented machine-name strings.
   */
  public function testEventNameConstants(): void {
    self::assertSame('openintranet_notifications.created', NotificationEvents::CREATED);
    self::assertSame('openintranet_notifications.queued', NotificationEvents::QUEUED);
    self::assertSame('openintranet_notifications.delivered', NotificationEvents::DELIVERED);
    self::assertSame('openintranet_notifications.failed', NotificationEvents::FAILED);
    self::assertSame('openintranet_notifications.permanently_failed', NotificationEvents::PERMANENTLY_FAILED);
    self::assertSame('openintranet_notifications.seen', NotificationEvents::SEEN);
    self::assertSame('openintranet_notifications.digest_ready', NotificationEvents::DIGEST_READY);
  }

  /**
   * Notification-carrying events construct and expose the notification.
   */
  public function testNotificationEventsCarryNotification(): void {
    $notification = Notification::create(['type' => 'default']);

    $created = new NotificationCreatedEvent($notification);
    self::assertSame($notification, $created->notification);

    $queued = new NotificationQueuedEvent($notification);
    self::assertSame($notification, $queued->notification);

    $seen = new NotificationSeenEvent($notification);
    self::assertSame($notification, $seen->notification);

    // Mirrors eca_base CustomEvent: implements the token-receiver contract.
    self::assertSame([], $created->getTokenNamesToReceive());
    $created->addTokenNamesToReceive(['foo']);
    self::assertSame(['foo'], $created->getTokenNamesToReceive());
  }

  /**
   * Delivery-carrying events construct and expose delivery and notification.
   */
  public function testDeliveryEventsCarryDelivery(): void {
    $notification = Notification::create(['type' => 'default']);
    $delivery = NotificationDelivery::create(['channel' => 'log_only']);

    $delivered = new NotificationDeliveredEvent($delivery, $notification);
    self::assertSame($delivery, $delivered->delivery);
    self::assertSame($notification, $delivered->notification);

    $failed = new NotificationFailedEvent($delivery, $notification);
    self::assertSame($delivery, $failed->delivery);
    self::assertSame($notification, $failed->notification);

    $permanently = new NotificationPermanentlyFailedEvent($delivery, $notification);
    self::assertSame($delivery, $permanently->delivery);
    self::assertSame($notification, $permanently->notification);

    // The parent notification is optional on delivery events.
    $deliveredNoParent = new NotificationDeliveredEvent($delivery);
    self::assertNull($deliveredNoParent->notification);
  }

  /**
   * The digest-ready event carries the recipient uid.
   */
  public function testDigestReadyEventCarriesUid(): void {
    $event = new NotificationDigestReadyEvent(42);
    self::assertSame(42, $event->uid);
  }

  /**
   * Notification events expose the notification via EntityEventInterface.
   *
   * ECA's EcaExecutionGeneralSubscriber reads getEntity() to expose
   * [entity:*]/[ENTITY_TYPE:*] tokens for the event's successors.
   */
  public function testNotificationEventsExposeEntity(): void {
    $notification = Notification::create(['type' => 'default']);

    self::assertInstanceOf(EntityEventInterface::class, new NotificationCreatedEvent($notification));
    self::assertSame($notification, (new NotificationCreatedEvent($notification))->getEntity());
    self::assertSame($notification, (new NotificationQueuedEvent($notification))->getEntity());
    self::assertSame($notification, (new NotificationSeenEvent($notification))->getEntity());
  }

  /**
   * Delivery events expose the delivery via EntityEventInterface.
   */
  public function testDeliveryEventsExposeDelivery(): void {
    $delivery = NotificationDelivery::create(['channel' => 'log_only']);

    self::assertInstanceOf(EntityEventInterface::class, new NotificationDeliveredEvent($delivery));
    self::assertSame($delivery, (new NotificationDeliveredEvent($delivery))->getEntity());
    self::assertSame($delivery, (new NotificationFailedEvent($delivery))->getEntity());
    self::assertSame($delivery, (new NotificationPermanentlyFailedEvent($delivery))->getEntity());
  }

}
