<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Event;

use Drupal\Core\Entity\EntityInterface;
use Drupal\eca\Event\EntityEventInterface;
use Drupal\eca\Event\TokenReceiverInterface;
use Drupal\eca\Event\TokenReceiverTrait;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a delivery attempt succeeds on a channel.
 *
 * Mirrors eca_base CustomEvent: implements TokenReceiverInterface so ECA can
 * preserve action-provided tokens across the event's successors. Implements
 * EntityEventInterface so ECA exposes [entity:*]/[ENTITY_TYPE:*] tokens for the
 * delivery row.
 */
final class NotificationDeliveredEvent extends Event implements TokenReceiverInterface, EntityEventInterface {

  use TokenReceiverTrait;

  /**
   * Constructs a NotificationDeliveredEvent.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $delivery
   *   The delivery that succeeded.
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface|null $notification
   *   The parent notification, when known.
   */
  public function __construct(
    public readonly NotificationDeliveryInterface $delivery,
    public readonly ?NotificationInterface $notification = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getEntity(): EntityInterface {
    return $this->delivery;
  }

}
