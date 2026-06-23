<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Channel;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\openintranet_notifications\Dto\DeliveryResult;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;

/**
 * Contract for a notification delivery channel.
 *
 * A channel knows how to address a recipient on its transport and how to send
 * a rendered message there (00-synteza §8).
 */
interface NotificationChannelInterface extends PluginInspectionInterface {

  /**
   * The channel plugin id.
   */
  public function getId(): string;

  /**
   * The human-readable channel label.
   */
  public function getLabel(): string;

  /**
   * Whether the channel is configured AND not killed by the global kill switch.
   */
  public function isAvailable(): bool;

  /**
   * The maximum number of send attempts before a failure becomes permanent.
   *
   * The per-channel retry budget (00-synteza §3.1: "max prób per kanał, np.
   * 5"). The shared DeliverySender reads this instead of a single global cap,
   * so a flaky transport can lower it (e.g. SMS → 2) without touching the
   * worker.
   * The base default is 5; channels override only when they need a tighter cap.
   *
   * @return int
   *   The maximum attempt count for this channel (>= 1).
   */
  public function maxAttempts(): int;

  /**
   * Extracts this channel's own address from the recipient.
   *
   * Each channel resolves the address it needs (email -> user mail / raw value;
   * sms -> user phone field / raw value). NULL means the channel cannot address
   * this recipient (00-synteza §8).
   *
   * @param \Drupal\openintranet_notifications\Dto\NotificationRecipient $recipient
   *   The recipient identity.
   *
   * @return string|null
   *   The transport address, or NULL when unaddressable.
   */
  public function getRecipientAddress(NotificationRecipient $recipient): ?string;

  /**
   * Whether getRecipientAddress() yields a usable address for the recipient.
   */
  public function canSendTo(NotificationRecipient $recipient): bool;

  /**
   * Delivers the message to the recipient.
   *
   * MUST classify the outcome via DeliveryResult and never throw on a transport
   * error — the queue worker decides whether to retry.
   *
   * @param \Drupal\openintranet_notifications\Dto\NotificationRecipient $recipient
   *   The recipient identity.
   * @param \Drupal\openintranet_notifications\Dto\NotificationMessage $message
   *   The rendered message.
   *
   * @return \Drupal\openintranet_notifications\Dto\DeliveryResult
   *   The classified delivery outcome.
   */
  public function send(NotificationRecipient $recipient, NotificationMessage $message): DeliveryResult;

}
