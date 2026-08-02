<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Service;

use Drupal\openintranet_messenger\Recipient\RecipientInterface;

/**
 * Interface for the notification service.
 */
interface NotificationServiceInterface {

  /**
   * Sends a notification to a single recipient.
   *
   * @param \Drupal\openintranet_messenger\Recipient\RecipientInterface $recipient
   *   The recipient.
   * @param string $subject
   *   The notification subject.
   * @param string $message
   *   The notification message.
   * @param string|null $channel
   *   The channel to use, or NULL to use recipient's preferred channel.
   *
   * @return \Drupal\openintranet_messenger\Service\SendResult
   *   The send result.
   */
  public function send(
    RecipientInterface $recipient,
    string $subject,
    string $message,
    ?string $channel = NULL,
  ): SendResult;

  /**
   * Sends notifications to multiple recipients (synchronous).
   *
   * @param \Drupal\openintranet_messenger\Recipient\RecipientInterface[] $recipients
   *   Array of recipients.
   * @param string $subject
   *   The notification subject.
   * @param string $message
   *   The notification message.
   * @param string|null $channel
   *   The channel to use, or NULL to use each recipient's preferred channel.
   *
   * @return \Drupal\openintranet_messenger\Service\BulkSendResult
   *   The bulk send result.
   */
  public function sendBulk(
    array $recipients,
    string $subject,
    string $message,
    ?string $channel = NULL,
  ): BulkSendResult;

  /**
   * Queues notifications for multiple recipients (asynchronous).
   *
   * This method adds items to the queue for background processing.
   * Use this for large batches to avoid blocking the request.
   *
   * @param \Drupal\openintranet_messenger\Recipient\RecipientInterface[] $recipients
   *   Array of recipients.
   * @param string $subject
   *   The notification subject.
   * @param string $message
   *   The notification message.
   * @param string|null $channel
   *   The channel to use, or NULL to use each recipient's preferred channel.
   *
   * @return int
   *   The number of items queued.
   */
  public function queueBulk(
    array $recipients,
    string $subject,
    string $message,
    ?string $channel = NULL,
  ): int;

}
