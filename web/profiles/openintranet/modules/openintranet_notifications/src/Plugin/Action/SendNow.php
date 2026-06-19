<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\openintranet_notifications\Channel\ChannelPluginManager;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sends a notification synchronously on one channel, bypassing the queue.
 *
 * Test/small-use only: it creates a single delivery row, calls the channel
 * directly and records the outcome. It deliberately carries no retry logic
 * (that lives in the queue worker) — a failure is recorded once and left.
 */
#[Action(
  id: 'openintranet_notifications_send_now',
  label: new TranslatableMarkup('Notification: send now (synchronous)'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Send a notification synchronously on one channel (test/small use only; no retry).'),
  version_introduced: '1.0.0',
)]
final class SendNow extends ConfigurableActionBase {

  use NotificationActionTrait;

  /**
   * The channel plugin manager.
   *
   * @var \Drupal\openintranet_notifications\Channel\ChannelPluginManager
   */
  protected ChannelPluginManager $channelManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->channelManager = $container->get('plugin.manager.notification_channel');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $notification = $this->resolveEntity((string) $this->configuration['notification']);
    if (!$notification instanceof NotificationInterface) {
      return;
    }
    $channelId = trim((string) $this->tokenService->replaceClear($this->configuration['channel']));
    if ($channelId === '' || !$this->channelManager->hasDefinition($channelId)) {
      return;
    }

    $recipient = $this->buildRecipient($notification);
    $channel = $this->channelManager->createInstance($channelId);

    /** @var \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $delivery */
    $delivery = $this->entityTypeManager->getStorage('openintranet_notif_delivery')->create([
      'notification_id' => $notification->id(),
      'recipient_type' => $recipient->type,
      'recipient_id' => $recipient->id,
      'contact_value' => $recipient->value,
      'channel' => $channelId,
      'address' => $channel->getRecipientAddress($recipient),
      'status' => 'pending',
      'idempotency_key' => hash('sha256', $notification->id() . ':' . (string) ($recipient->id ?? $recipient->value) . ':' . $channelId),
    ]);

    $message = new NotificationMessage(
      subject: (string) $notification->get('subject')->value,
      body: (string) $notification->get('body')->value,
      summary: (string) $notification->get('summary')->value,
      payload: $notification->get('payload')->first()?->getValue() ?? [],
    );

    $result = $channel->send($recipient, $message);
    $delivery->set('attempt_count', 1);
    if ($result->success) {
      $delivery->markSent($result->providerMessageId);
    }
    else {
      $delivery->markFailed($result);
    }
    $delivery->save();
  }

  /**
   * Builds the recipient DTO from the notification's recipient uid.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface $notification
   *   The notification.
   *
   * @return \Drupal\openintranet_notifications\Dto\NotificationRecipient
   *   The recipient identity.
   */
  private function buildRecipient(NotificationInterface $notification): NotificationRecipient {
    $uid = (int) $notification->get('uid')->target_id;
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    return new NotificationRecipient(
      type: 'user',
      id: $uid,
      langcode: $user !== NULL ? $user->getPreferredLangcode() : 'en',
      account: $user,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'notification' => '',
      'channel' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['notification'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Notification'),
      '#description' => $this->t('A token resolving to the notification entity to send.'),
      '#default_value' => $this->configuration['notification'],
      '#eca_token_replacement' => TRUE,
      '#required' => TRUE,
    ];
    $form['channel'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Channel'),
      '#description' => $this->t('The channel plugin id to send on.'),
      '#default_value' => $this->configuration['channel'],
      '#eca_token_replacement' => TRUE,
      '#required' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['notification'] = $form_state->getValue('notification');
    $this->configuration['channel'] = $form_state->getValue('channel');
    parent::submitConfigurationForm($form, $form_state);
  }

}
