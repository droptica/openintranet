<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\openintranet_notifications\Dto\DeliveryResult;

/**
 * Provides an interface for the notification_delivery content entity.
 *
 * One recipient × one channel attempt; the audit-critical record of what was
 * sent where and how it went (00-synteza §4.3). The queue worker drives its
 * lifecycle via the helpers below.
 */
interface NotificationDeliveryInterface extends ContentEntityInterface {

  /**
   * Marks the delivery as sent.
   *
   * @param string|null $providerMessageId
   *   The id the transport assigned to the message, if any.
   */
  public function markSent(?string $providerMessageId = NULL): void;

  /**
   * Marks the delivery as failed, recording the error from the result.
   *
   * @param \Drupal\openintranet_notifications\Dto\DeliveryResult $result
   *   The failed delivery outcome carrying the error code and message.
   */
  public function markFailed(DeliveryResult $result): void;

  /**
   * Schedules a retry, keeping the status pending.
   *
   * @param int $delaySec
   *   The backoff delay in seconds; next_attempt becomes now + delaySec.
   */
  public function scheduleRetry(int $delaySec): void;

  /**
   * Whether the delivery has reached a terminal state.
   *
   * Terminal means no further send attempt is allowed: sent, delivered or
   * cancelled.
   */
  public function isTerminal(): bool;

}
