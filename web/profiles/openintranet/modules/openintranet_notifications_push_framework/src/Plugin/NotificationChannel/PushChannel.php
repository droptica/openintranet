<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications_push_framework\Plugin\NotificationChannel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationChannel;
use Drupal\openintranet_notifications\Channel\NotificationChannelBase;
use Drupal\openintranet_notifications\Dto\DeliveryResult;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\push_framework\ChannelPluginInterface;
use Drupal\push_framework\ChannelPluginManager;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Delegates push notifications to Push Framework's registered channels.
 *
 * Push Framework is an orchestrator, not a per-message transport: it has no
 * "send this message to this user now" service. This adapter bridges the
 * impedance mismatch by iterating Push Framework's active, applicable channel
 * plugins for the recipient's user account and mapping their RESULT_STATUS_*
 * back to a DeliveryResult. See this submodule's README for the full mismatch
 * write-up and the notification-entity limitation.
 */
#[NotificationChannel(
  id: 'push',
  label: new TranslatableMarkup('Push (Push Framework)'),
  description: new TranslatableMarkup("Delivers via Push Framework's registered channel plugins for the recipient."),
)]
final class PushChannel extends NotificationChannelBase {

  /**
   * Constructs a PushChannel.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\push_framework\ChannelPluginManager $pfChannelManager
   *   The Push Framework channel plugin manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    private readonly ChannelPluginManager $pfChannelManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('push_framework.channel.plugin.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientAddress(NotificationRecipient $recipient): ?string {
    // Push targets a Drupal user; only user recipients are addressable.
    return $recipient->isUser() ? (string) $recipient->id : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    try {
      foreach (array_keys($this->pfChannelManager->getDefinitions() ?? []) as $id) {
        $plugin = $this->pfChannelManager->createInstance($id);
        if ($plugin instanceof ChannelPluginInterface && $plugin->isActive()) {
          return TRUE;
        }
      }
    }
    catch (\Throwable) {
      return FALSE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function canSendTo(NotificationRecipient $recipient): bool {
    if (!$recipient->isUser() || $recipient->account === NULL) {
      return FALSE;
    }
    $user = $recipient->account;
    if (!$user instanceof UserInterface) {
      return FALSE;
    }
    try {
      foreach (array_keys($this->pfChannelManager->getDefinitions() ?? []) as $id) {
        $plugin = $this->pfChannelManager->createInstance($id);
        if ($plugin instanceof ChannelPluginInterface && $plugin->isActive() && $plugin->applicable($user)) {
          return TRUE;
        }
      }
    }
    catch (\Throwable) {
      return FALSE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationRecipient $recipient, NotificationMessage $message): DeliveryResult {
    if (!$recipient->isUser() || $recipient->account === NULL) {
      return DeliveryResult::permanentFailure('NO_USER', 'Push channel requires a user recipient.');
    }
    $user = $recipient->account;
    if (!$user instanceof UserInterface) {
      return DeliveryResult::permanentFailure('NO_USER', 'Push channel requires a loaded user account.');
    }

    // Push Framework's send() requires the source entity it pushes about. Our
    // frozen contract does not pass it, so accept a best-effort entity from the
    // message payload and otherwise degrade (see README). @todo A future
    // contract revision should pass the notification entity to send().
    $entity = $message->payload['entity'] ?? NULL;
    if (!$entity instanceof ContentEntityInterface) {
      return DeliveryResult::permanentFailure('PUSH_NO_ENTITY', 'No source entity available for the push channel.');
    }

    $content = [
      'subject' => ['#markup' => $message->subject],
      'body' => ['#markup' => $message->body],
    ];

    try {
      $sawRetry = FALSE;
      $sawFailed = FALSE;
      $applicable = FALSE;

      foreach (array_keys($this->pfChannelManager->getDefinitions() ?? []) as $id) {
        $plugin = $this->pfChannelManager->createInstance($id);
        if (!$plugin instanceof ChannelPluginInterface || !$plugin->isActive() || !$plugin->applicable($user)) {
          continue;
        }
        $applicable = TRUE;
        $status = $plugin->send($user, $entity, $content, 0);
        if ($status === ChannelPluginInterface::RESULT_STATUS_SUCCESS) {
          return DeliveryResult::success();
        }
        if ($status === ChannelPluginInterface::RESULT_STATUS_RETRY) {
          $sawRetry = TRUE;
        }
        elseif ($status === ChannelPluginInterface::RESULT_STATUS_FAILED) {
          $sawFailed = TRUE;
        }
      }

      if (!$applicable) {
        return DeliveryResult::permanentFailure('NO_PUSH_CHANNEL', 'No applicable push channel for recipient.');
      }
      if ($sawRetry) {
        return DeliveryResult::retryableFailure('PUSH_RETRY', 'A push channel reported a retryable failure.');
      }
      if ($sawFailed) {
        return DeliveryResult::permanentFailure('PUSH_FAILED', 'A push channel reported a permanent failure.');
      }
      // Applicable channels ran but reported no recognised status.
      return DeliveryResult::permanentFailure('PUSH_FAILED', 'Push channels returned no recognised status.');
    }
    catch (\Throwable $e) {
      return DeliveryResult::retryableFailure('PUSH_EXCEPTION', $e->getMessage());
    }
  }

}
