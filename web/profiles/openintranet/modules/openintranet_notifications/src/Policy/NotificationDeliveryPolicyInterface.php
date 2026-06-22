<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Policy;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;

/**
 * Contract for a delivery policy plugin.
 *
 * A policy decides which channels a given recipient should receive a given
 * notification type on (00-synteza §3.2). It owns the channel-set arithmetic;
 * channels stay unaware of preferences, kill switches and availability rules.
 */
interface NotificationDeliveryPolicyInterface extends PluginInspectionInterface {

  /**
   * The policy plugin id.
   */
  public function getId(): string;

  /**
   * The human-readable policy label.
   */
  public function getLabel(): string;

  /**
   * Selects the channels to deliver on for one recipient.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationTypeInterface $type
   *   The notification type carrying default/forced channels and knobs.
   * @param \Drupal\openintranet_notifications\Dto\NotificationRecipient $recipient
   *   The recipient identity.
   * @param array $context
   *   The dispatch context.
   *
   * @return string[]
   *   The selected channel plugin ids.
   */
  public function selectChannels(NotificationTypeInterface $type, NotificationRecipient $recipient, array $context): array;

  /**
   * What an empty channel selection from this policy means.
   *
   * The dispatcher consults this only when selectChannels() returns []: Drop
   * (the default) is a true drop stamped 'cancelled'; Defer and Audit are
   * intentional empty sets that still persist and fire the created event.
   *
   * @return \Drupal\openintranet_notifications\Policy\EmptySelectionDisposition
   *   The disposition for an empty selection.
   */
  public function emptySelectionDisposition(): EmptySelectionDisposition;

}
