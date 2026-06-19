<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\ECA\Event;

use Drupal\eca\Attribute\EcaEvent;
use Drupal\eca\Event\Tag;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\openintranet_notifications\Event\NotificationCreatedEvent;
use Drupal\openintranet_notifications\Event\NotificationDeliveredEvent;
use Drupal\openintranet_notifications\Event\NotificationDigestReadyEvent;
use Drupal\openintranet_notifications\Event\NotificationEvents;
use Drupal\openintranet_notifications\Event\NotificationFailedEvent;
use Drupal\openintranet_notifications\Event\NotificationPermanentlyFailedEvent;
use Drupal\openintranet_notifications\Event\NotificationQueuedEvent;
use Drupal\openintranet_notifications\Event\NotificationSeenEvent;

/**
 * Plugin implementation of the notification lifecycle ECA events.
 */
#[EcaEvent(
  id: 'notification',
  deriver: NotificationEventDeriver::class,
  version_introduced: '1.0.0',
)]
class NotificationEvent extends EventBase {

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    return [
      'created' => [
        'label' => 'Notification created',
        'event_name' => NotificationEvents::CREATED,
        'event_class' => NotificationCreatedEvent::class,
        'tags' => Tag::CONTENT | Tag::AFTER,
      ],
      'queued' => [
        'label' => 'Notification queued',
        'event_name' => NotificationEvents::QUEUED,
        'event_class' => NotificationQueuedEvent::class,
        'tags' => Tag::CONTENT | Tag::AFTER,
      ],
      'delivered' => [
        'label' => 'Notification delivered',
        'event_name' => NotificationEvents::DELIVERED,
        'event_class' => NotificationDeliveredEvent::class,
        'tags' => Tag::AFTER,
      ],
      'failed' => [
        'label' => 'Notification delivery failed',
        'event_name' => NotificationEvents::FAILED,
        'event_class' => NotificationFailedEvent::class,
        'tags' => Tag::AFTER,
      ],
      'permanently_failed' => [
        'label' => 'Notification delivery permanently failed',
        'event_name' => NotificationEvents::PERMANENTLY_FAILED,
        'event_class' => NotificationPermanentlyFailedEvent::class,
        'tags' => Tag::AFTER,
      ],
      'seen' => [
        'label' => 'Notification seen',
        'event_name' => NotificationEvents::SEEN,
        'event_class' => NotificationSeenEvent::class,
        'tags' => Tag::CONTENT | Tag::AFTER,
      ],
      'digest_ready' => [
        'label' => 'Notification digest ready',
        'event_name' => NotificationEvents::DIGEST_READY,
        'event_class' => NotificationDigestReadyEvent::class,
        'tags' => Tag::AFTER,
      ],
    ];
  }

}
