<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\openintranet_messenger\Channel\ChannelPluginManager;
use Drupal\openintranet_messenger\Exception\ChannelException;
use Drupal\openintranet_messenger\NotificationLogInterface;
use Drupal\openintranet_messenger\Recipient\RecipientInterface;
use Drupal\openintranet_messenger\Recipient\RecipientResolver;

/**
 * Service for sending notifications.
 */
final class NotificationService implements NotificationServiceInterface {

  /**
   * The queue name.
   */
  public const QUEUE_NAME = 'openintranet_messenger_notification';

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private LoggerChannelInterface $logger;

  /**
   * Constructs a NotificationService object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\openintranet_messenger\Channel\ChannelPluginManager $channelManager
   *   The channel plugin manager.
   * @param \Drupal\openintranet_messenger\Recipient\RecipientResolver $recipientResolver
   *   The recipient resolver.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory.
   */
  public function __construct(
    LoggerChannelFactoryInterface $loggerFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ChannelPluginManager $channelManager,
    private readonly RecipientResolver $recipientResolver,
    private readonly AccountProxyInterface $currentUser,
    private readonly QueueFactory $queueFactory,
  ) {
    $this->logger = $loggerFactory->get('openintranet_messenger');
  }

  /**
   * {@inheritdoc}
   */
  public function send(
    RecipientInterface $recipient,
    string $subject,
    string $message,
    ?string $channel = NULL,
  ): SendResult {
    // Determine channel to use.
    $channel_id = $channel ?? $recipient->getPreferredChannel();

    // Handle "both" channel - send email and SMS.
    if ($channel_id === 'both') {
      // Try email first, then SMS.
      $result = $this->sendViaChannel($recipient, $subject, $message, 'email');
      if ($result->success) {
        // Also try SMS.
        $sms_result = $this->sendViaChannel($recipient, $subject, $message, 'sms');
        // Return email result (primary).
        return $result;
      }
      // Email failed, try SMS.
      return $this->sendViaChannel($recipient, $subject, $message, 'sms');
    }

    return $this->sendViaChannel($recipient, $subject, $message, $channel_id);
  }

  /**
   * Sends via a specific channel.
   *
   * @param \Drupal\openintranet_messenger\Recipient\RecipientInterface $recipient
   *   The recipient.
   * @param string $subject
   *   The subject.
   * @param string $message
   *   The message.
   * @param string $channel_id
   *   The channel ID.
   *
   * @return \Drupal\openintranet_messenger\Service\SendResult
   *   The result.
   */
  private function sendViaChannel(
    RecipientInterface $recipient,
    string $subject,
    string $message,
    string $channel_id,
  ): SendResult {
    $this->logger->info('Messenger: Processing recipient @name (@type #@id)', [
      '@name' => $recipient->getName(),
      '@type' => $recipient->getSourceInfo()['type'],
      '@id' => $recipient->getSourceInfo()['id'],
    ]);

    // Check if channel exists.
    if (!$this->channelManager->hasDefinition($channel_id)) {
      $this->logger->notice('Messenger: Channel @channel not found', [
        '@channel' => $channel_id,
      ]);
      return SendResult::failure($recipient, $channel_id, 'Channel not found');
    }

    /** @var \Drupal\openintranet_messenger\Channel\ChannelPluginInterface $channel */
    $channel = $this->channelManager->createInstance($channel_id);

    // Check if channel is available.
    if (!$channel->isAvailable()) {
      $this->logger->notice('Messenger: Channel @channel is not available', [
        '@channel' => $channel_id,
      ]);
      return SendResult::failure($recipient, $channel_id, 'Channel not available');
    }

    // Get recipient address.
    $address = $channel->getRecipientAddress($recipient);

    if (empty($address)) {
      $this->logger->notice('Messenger: Skipping recipient @name - no valid address for channel @channel', [
        '@name' => $recipient->getName(),
        '@channel' => $channel_id,
      ]);
      $this->createLog($recipient, $channel_id, $address, $subject, $message, NotificationLogInterface::STATUS_FAILED, 'No valid address');
      return SendResult::skipped($recipient, $channel_id, 'No valid address');
    }

    $this->logger->info('Messenger: Recipient @name - resolved channel: @channel, address: @address', [
      '@name' => $recipient->getName(),
      '@channel' => $channel_id,
      '@address' => $address,
    ]);

    // Attempt to send.
    try {
      $channel->send($recipient, $subject, $message);

      $this->logger->info('Messenger: Successfully sent to @name via @channel (@address). Subject: "@subject". Message: "@message"', [
        '@name' => $recipient->getName(),
        '@channel' => $channel_id,
        '@address' => $address,
        '@subject' => $subject,
        '@message' => $message,
      ]);

      $this->createLog($recipient, $channel_id, $address, $subject, $message, NotificationLogInterface::STATUS_SENT);

      return SendResult::success($recipient, $channel_id, $address);
    }
    catch (ChannelException $e) {
      $this->logger->error('Messenger: Failed to send to @name via @channel. Error: @error. Subject: "@subject". Message: "@message"', [
        '@name' => $recipient->getName(),
        '@channel' => $channel_id,
        '@error' => $e->getMessage(),
        '@subject' => $subject,
        '@message' => $message,
      ]);

      $this->createLog($recipient, $channel_id, $address, $subject, $message, NotificationLogInterface::STATUS_FAILED, $e->getMessage());

      return SendResult::failure($recipient, $channel_id, $e->getMessage(), $address);
    }
    catch (\Exception $e) {
      $this->logger->error('Messenger: Unexpected error sending to @name via @channel. Error: @error', [
        '@name' => $recipient->getName(),
        '@channel' => $channel_id,
        '@error' => $e->getMessage(),
      ]);

      $this->createLog($recipient, $channel_id, $address, $subject, $message, NotificationLogInterface::STATUS_FAILED, $e->getMessage());

      return SendResult::failure($recipient, $channel_id, $e->getMessage(), $address);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function sendBulk(
    array $recipients,
    string $subject,
    string $message,
    ?string $channel = NULL,
  ): BulkSendResult {
    $result = new BulkSendResult();

    $channel_display = $channel ?? 'recipient preference';
    $this->logger->info('Messenger: Starting bulk send to @count recipients. Channel: @channel', [
      '@count' => count($recipients),
      '@channel' => $channel_display,
    ]);

    foreach ($recipients as $recipient) {
      if (!$recipient instanceof RecipientInterface) {
        continue;
      }

      $send_result = $this->send($recipient, $subject, $message, $channel);
      $result->addResult($send_result);
    }

    $this->logger->info('Messenger: Bulk send completed. Sent: @sent, Failed: @failed, Skipped: @skipped', [
      '@sent' => $result->getSentCount(),
      '@failed' => $result->getFailedCount(),
      '@skipped' => $result->getSkippedCount(),
    ]);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function queueBulk(
    array $recipients,
    string $subject,
    string $message,
    ?string $channel = NULL,
  ): int {
    $queue = $this->queueFactory->get(self::QUEUE_NAME);
    $queued = 0;
    $sent_by = (int) $this->currentUser->id();

    $channel_display = $channel ?? 'recipient preference';
    $this->logger->info('Messenger: Queueing bulk send to @count recipients. Channel: @channel', [
      '@count' => count($recipients),
      '@channel' => $channel_display,
    ]);

    foreach ($recipients as $recipient) {
      if (!$recipient instanceof RecipientInterface) {
        continue;
      }

      $source_info = $recipient->getSourceInfo();

      $item = [
        'recipient_type' => $source_info['type'],
        'recipient_id' => $source_info['id'],
        'subject' => $subject,
        'message' => $message,
        'channel' => $channel,
        'sent_by' => $sent_by,
        'queued_at' => time(),
      ];

      if ($queue->createItem($item)) {
        $queued++;
        $this->logger->info('Messenger: Queued notification for @name (@type #@id)', [
          '@name' => $recipient->getName(),
          '@type' => $source_info['type'],
          '@id' => $source_info['id'],
        ]);
      }
      else {
        $this->logger->error('Messenger: Failed to queue notification for @name', [
          '@name' => $recipient->getName(),
        ]);
      }
    }

    $this->logger->info('Messenger: Queued @count items for processing', [
      '@count' => $queued,
    ]);

    return $queued;
  }

  /**
   * Creates a notification log entry.
   *
   * @param \Drupal\openintranet_messenger\Recipient\RecipientInterface $recipient
   *   The recipient.
   * @param string $channel
   *   The channel.
   * @param string|null $address
   *   The address.
   * @param string $subject
   *   The subject.
   * @param string $message
   *   The message.
   * @param string $status
   *   The status.
   * @param string|null $error
   *   The error message.
   */
  private function createLog(
    RecipientInterface $recipient,
    string $channel,
    ?string $address,
    string $subject,
    string $message,
    string $status,
    ?string $error = NULL,
  ): void {
    try {
      $source_info = $recipient->getSourceInfo();

      /** @var \Drupal\openintranet_messenger\Entity\NotificationLog $log */
      $log = $this->entityTypeManager->getStorage('notification_log')->create([
        'recipient_type' => $source_info['type'],
        'recipient_id' => $source_info['id'],
        'recipient_name' => $recipient->getName(),
        'channel' => $channel,
        'recipient_address' => $address ?? '',
        'subject' => $subject,
        'message' => $message,
        'status' => $status,
        'error_message' => $error,
        'sent_at' => time(),
        'sent_by' => $this->currentUser->id(),
      ]);

      $log->save();
    }
    catch (\Exception $e) {
      $this->logger->error('Messenger: Failed to create log entry. Error: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

}
