<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Event;

use Drupal\eca\Event\TokenReceiverInterface;
use Drupal\eca\Event\TokenReceiverTrait;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when a recipient marks a notification as seen.
 *
 * Mirrors eca_base CustomEvent: implements TokenReceiverInterface so ECA can
 * preserve action-provided tokens across the event's successors.
 */
final class NotificationSeenEvent extends Event implements TokenReceiverInterface {

  use TokenReceiverTrait;

  /**
   * Constructs a NotificationSeenEvent.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface $notification
   *   The seen notification.
   */
  public function __construct(
    public readonly NotificationInterface $notification,
  ) {}

}
