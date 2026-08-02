<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Event;

use Drupal\eca\Event\TokenReceiverInterface;
use Drupal\eca\Event\TokenReceiverTrait;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when a digest is ready to be compiled for a recipient.
 *
 * Stage 6 fills the digest payload; here the event only carries the recipient.
 * Mirrors eca_base CustomEvent: implements TokenReceiverInterface so ECA can
 * preserve action-provided tokens across the event's successors.
 */
final class NotificationDigestReadyEvent extends Event implements TokenReceiverInterface {

  use TokenReceiverTrait;

  /**
   * Constructs a NotificationDigestReadyEvent.
   *
   * @param int $uid
   *   The recipient user id the digest is for.
   */
  public function __construct(
    public readonly int $uid,
  ) {}

}
