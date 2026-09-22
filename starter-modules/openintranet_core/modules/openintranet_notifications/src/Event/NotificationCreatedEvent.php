<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Event;

use Drupal\Core\Entity\EntityInterface;
use Drupal\eca\Event\EntityEventInterface;
use Drupal\eca\Event\TokenReceiverInterface;
use Drupal\eca\Event\TokenReceiverTrait;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a notification entity is created.
 *
 * Mirrors eca_base CustomEvent: implements TokenReceiverInterface so ECA can
 * preserve action-provided tokens across the event's successors. Implements
 * EntityEventInterface so ECA exposes [entity:*]/[ENTITY_TYPE:*] tokens for the
 * notification.
 */
final class NotificationCreatedEvent extends Event implements TokenReceiverInterface, EntityEventInterface {

  use TokenReceiverTrait;

  /**
   * Constructs a NotificationCreatedEvent.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface $notification
   *   The created notification.
   */
  public function __construct(
    public readonly NotificationInterface $notification,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getEntity(): EntityInterface {
    return $this->notification;
  }

}
