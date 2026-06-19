<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Channel;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;

/**
 * Base class for notification channel plugins.
 *
 * Concrete channels implement ContainerFactoryPluginInterface::create() to
 * receive their own service dependencies.
 */
abstract class NotificationChannelBase extends PluginBase implements NotificationChannelInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) ($this->getPluginDefinition()['label'] ?? $this->getPluginId());
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    // Channel-config availability only; the global kill switch is enforced by
    // the delivery policy. Real channels override this with their own check.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function canSendTo(NotificationRecipient $recipient): bool {
    return $this->getRecipientAddress($recipient) !== NULL;
  }

}
