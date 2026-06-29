<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for the notification lifecycle ECA event plugins.
 */
class NotificationEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return NotificationEvent::definitions();
  }

}
