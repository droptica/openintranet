<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Event;

/**
 * Defines the Symfony event names for the notification lifecycle.
 *
 * Each constant is the dispatched event name and the declared event_name of the
 * matching ECA derivative (notification:<key>); the two must stay in sync.
 */
final class NotificationEvents {

  /**
   * A notification entity was created (one recipient row).
   *
   * @Event
   *
   * @var string
   */
  public const CREATED = 'openintranet_notifications.created';

  /**
   * A notification was enqueued for channel delivery.
   *
   * @Event
   *
   * @var string
   */
  public const QUEUED = 'openintranet_notifications.queued';

  /**
   * A delivery attempt succeeded on a channel.
   *
   * @Event
   *
   * @var string
   */
  public const DELIVERED = 'openintranet_notifications.delivered';

  /**
   * A delivery attempt failed but may be retried.
   *
   * @Event
   *
   * @var string
   */
  public const FAILED = 'openintranet_notifications.failed';

  /**
   * A delivery exhausted its retries and will not be attempted again.
   *
   * @Event
   *
   * @var string
   */
  public const PERMANENTLY_FAILED = 'openintranet_notifications.permanently_failed';

  /**
   * A recipient marked a notification as seen.
   *
   * @Event
   *
   * @var string
   */
  public const SEEN = 'openintranet_notifications.seen';

  /**
   * A digest is ready to be compiled for a recipient (Stage 6 fills it).
   *
   * @Event
   *
   * @var string
   */
  public const DIGEST_READY = 'openintranet_notifications.digest_ready';

}
