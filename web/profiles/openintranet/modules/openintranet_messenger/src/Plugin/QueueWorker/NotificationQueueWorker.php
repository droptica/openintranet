<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\openintranet_messenger\Channel\ChannelPluginManager;
use Drupal\openintranet_messenger\Exception\ChannelException;
use Drupal\openintranet_messenger\NotificationLogInterface;
use Drupal\openintranet_messenger\Recipient\RecipientResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes notification queue items.
 *
 * @QueueWorker(
 *   id = "openintranet_messenger_notification",
 *   title = @Translation("Messenger Notification Queue"),
 *   cron = {"time" = 60}
 * )
 */
final class NotificationQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private LoggerChannelInterface $logger;

  /**
   * Constructs a NotificationQueueWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\openintranet_messenger\Channel\ChannelPluginManager $channelManager
   *   The channel plugin manager.
   * @param \Drupal\openintranet_messenger\Recipient\RecipientResolver $recipientResolver
   *   The recipient resolver.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerChannelFactoryInterface $loggerFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ChannelPluginManager $channelManager,
    private readonly RecipientResolver $recipientResolver,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $loggerFactory->get('openintranet_messenger');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.notification_channel'),
      $container->get('openintranet_messenger.recipient_resolver'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    // Validate data structure.
    if (!isset($data['recipient_type'], $data['recipient_id'], $data['subject'], $data['message'])) {
      $this->logger->error('Messenger/Queue: Invalid queue item data');
      return;
    }

    $this->logger->info('Messenger/Queue: Processing item for @type #@id', [
      '@type' => $data['recipient_type'],
      '@id' => $data['recipient_id'],
    ]);

    // Load the recipient entity.
    $entity_type = $data['recipient_type'] === 'user' ? 'user' : 'messenger_contact';
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($data['recipient_id']);

    if (!$entity) {
      $this->logger->warning('Messenger/Queue: Recipient @type #@id not found', [
        '@type' => $data['recipient_type'],
        '@id' => $data['recipient_id'],
      ]);
      return;
    }

    // Resolve to RecipientInterface.
    $recipient = $this->recipientResolver->resolve($entity);

    if (!$recipient) {
      $this->logger->warning('Messenger/Queue: Could not resolve recipient @type #@id', [
        '@type' => $data['recipient_type'],
        '@id' => $data['recipient_id'],
      ]);
      return;
    }

    // Determine channel.
    $channel_id = $data['channel'] ?? $recipient->getPreferredChannel();

    // Handle "both" channel.
    if ($channel_id === 'both') {
      $this->sendViaChannel($recipient, $data['subject'], $data['message'], 'email', $data['sent_by'] ?? 0);
      $this->sendViaChannel($recipient, $data['subject'], $data['message'], 'sms', $data['sent_by'] ?? 0);
      return;
    }

    $this->sendViaChannel($recipient, $data['subject'], $data['message'], $channel_id, $data['sent_by'] ?? 0);
  }

  /**
   * Sends notification via a specific channel.
   *
   * @param \Drupal\openintranet_messenger\Recipient\RecipientInterface $recipient
   *   The recipient.
   * @param string $subject
   *   The subject.
   * @param string $message
   *   The message.
   * @param string $channel_id
   *   The channel ID.
   * @param int $sent_by
   *   The user ID who initiated the send.
   */
  private function sendViaChannel(
    $recipient,
    string $subject,
    string $message,
    string $channel_id,
    int $sent_by,
  ): void {
    // Check if channel exists.
    if (!$this->channelManager->hasDefinition($channel_id)) {
      $this->logger->notice('Messenger/Queue: Channel @channel not found', [
        '@channel' => $channel_id,
      ]);
      return;
    }

    /** @var \Drupal\openintranet_messenger\Channel\ChannelPluginInterface $channel */
    $channel = $this->channelManager->createInstance($channel_id);

    // Check if channel is available.
    if (!$channel->isAvailable()) {
      $this->logger->notice('Messenger/Queue: Channel @channel is not available', [
        '@channel' => $channel_id,
      ]);
      return;
    }

    // Get recipient address.
    $address = $channel->getRecipientAddress($recipient);

    if (empty($address)) {
      $this->logger->notice('Messenger/Queue: Skipping recipient @name - no valid address for channel @channel', [
        '@name' => $recipient->getName(),
        '@channel' => $channel_id,
      ]);
      $this->createLog($recipient, $channel_id, $address, $subject, $message, NotificationLogInterface::STATUS_FAILED, 'No valid address', $sent_by);
      return;
    }

    $this->logger->info('Messenger/Queue: Sending to @name via @channel (@address)', [
      '@name' => $recipient->getName(),
      '@channel' => $channel_id,
      '@address' => $address,
    ]);

    // Attempt to send.
    try {
      $channel->send($recipient, $subject, $message);

      $this->logger->info('Messenger/Queue: Successfully sent to @name via @channel (@address)', [
        '@name' => $recipient->getName(),
        '@channel' => $channel_id,
        '@address' => $address,
      ]);

      $this->createLog($recipient, $channel_id, $address, $subject, $message, NotificationLogInterface::STATUS_SENT, NULL, $sent_by);
    }
    catch (ChannelException $e) {
      $this->logger->error('Messenger/Queue: Failed to send to @name via @channel. Error: @error', [
        '@name' => $recipient->getName(),
        '@channel' => $channel_id,
        '@error' => $e->getMessage(),
      ]);

      $this->createLog($recipient, $channel_id, $address, $subject, $message, NotificationLogInterface::STATUS_FAILED, $e->getMessage(), $sent_by);
    }
    catch (\Exception $e) {
      $this->logger->error('Messenger/Queue: Unexpected error sending to @name via @channel. Error: @error', [
        '@name' => $recipient->getName(),
        '@channel' => $channel_id,
        '@error' => $e->getMessage(),
      ]);

      $this->createLog($recipient, $channel_id, $address, $subject, $message, NotificationLogInterface::STATUS_FAILED, $e->getMessage(), $sent_by);
    }
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
   * @param int $sent_by
   *   The user ID who initiated the send.
   */
  private function createLog(
    $recipient,
    string $channel,
    ?string $address,
    string $subject,
    string $message,
    string $status,
    ?string $error,
    int $sent_by,
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
        'sent_by' => $sent_by,
      ]);

      $log->save();
    }
    catch (\Exception $e) {
      $this->logger->error('Messenger/Queue: Failed to create log entry. Error: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

}
