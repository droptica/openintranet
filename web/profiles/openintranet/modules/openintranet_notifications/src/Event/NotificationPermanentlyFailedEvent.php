<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Event;

use Drupal\eca\Event\TokenReceiverInterface;
use Drupal\eca\Event\TokenReceiverTrait;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a delivery exhausts its retries and is abandoned.
 *
 * Mirrors eca_base CustomEvent: implements TokenReceiverInterface so ECA can
 * preserve action-provided tokens across the event's successors.
 */
final class NotificationPermanentlyFailedEvent extends Event implements TokenReceiverInterface {

  use TokenReceiverTrait;

  /**
   * Constructs a NotificationPermanentlyFailedEvent.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $delivery
   *   The delivery that permanently failed.
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface|null $notification
   *   The parent notification, when known.
   */
  public function __construct(
    public readonly NotificationDeliveryInterface $delivery,
    public readonly ?NotificationInterface $notification = NULL,
  ) {}

}
