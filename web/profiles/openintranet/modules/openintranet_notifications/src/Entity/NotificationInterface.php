<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for the openintranet_notification content entity.
 *
 * One notification = one recipient = one inbox row. The entity is the runtime
 * record and the inbox source of truth for the bell (00-synteza §4.2); the
 * "inbox" channel is simply the existence of this entity for a user.
 */
interface NotificationInterface extends ContentEntityInterface {

  /**
   * Marks the notification as read, stamping read_at with the current time.
   */
  public function setRead(): void;

  /**
   * Marks the notification as seen, stamping seen_at with the current time.
   */
  public function setSeen(): void;

  /**
   * Whether the notification has been read (read_at is set).
   */
  public function isRead(): bool;

}
