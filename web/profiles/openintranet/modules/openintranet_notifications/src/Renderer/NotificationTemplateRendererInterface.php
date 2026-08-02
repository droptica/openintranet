<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Renderer;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;

/**
 * Contract for a template renderer plugin.
 *
 * A renderer turns a notification type's templates and token data into a
 * channel-agnostic rendered message (00-synteza §8). The notification is
 * rendered once and stored; channels read the resulting DTO at send time.
 */
interface NotificationTemplateRendererInterface extends PluginInspectionInterface {

  /**
   * Renders the message for a notification type on a channel.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationTypeInterface $type
   *   The notification type carrying the templates.
   * @param string $channelId
   *   The target channel plugin id.
   * @param array $tokenData
   *   The token replacement data.
   *
   * @return \Drupal\openintranet_notifications\Dto\NotificationMessage
   *   The rendered message.
   */
  public function render(NotificationTypeInterface $type, string $channelId, array $tokenData): NotificationMessage;

}
