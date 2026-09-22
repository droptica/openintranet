<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\openintranet_notifications\Channel\ChannelPluginManager;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Service\DeliveryQueue;
use Drupal\openintranet_notifications\Service\DeliverySender;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sends a notification synchronously on one channel, bypassing the queue.
 *
 * Test/small-use only: it builds a single delivery row and hands it to the
 * shared DeliverySender, which runs the SAME idempotency claim, terminal and
 * channel-availability guards as the queue worker — so SendNow can never
 * double-send or diverge from the async path. It only differs in WHEN it runs
 * (synchronously, no queue) and that it does not translate a retryable failure
 * into a backoff re-queue: a failure is recorded once and left.
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
   * The delivery queue service (builds the shared delivery row).
   *
   * @var \Drupal\openintranet_notifications\Service\DeliveryQueue
   */
  protected DeliveryQueue $deliveryQueue;

  /**
   * The shared per-delivery sender (claim, guards, send, classify, roll-up).
   *
   * @var \Drupal\openintranet_notifications\Service\DeliverySender
   */
  protected DeliverySender $deliverySender;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->channelManager = $container->get('plugin.manager.openintranet_notification_channel');
    $instance->deliveryQueue = $container->get('openintranet_notifications.delivery_queue');
    $instance->deliverySender = $container->get('openintranet_notifications.delivery_sender');
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

    // Reuse the service's single delivery-row builder (shared idempotency-key
    // formula), persist it, then hand it to the shared sender so the same
    // claim, terminal and channel-availability guards run as in the worker — no
    // double-send, no divergent logic. SendNow does not re-queue a retryable
    // failure; the sender records it once and the row is left as-is.
    $delivery = $this->deliveryQueue->createDeliveryRow($notification, $recipient, $channelId);
    $delivery->save();
    $this->deliverySender->send($delivery);
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
    return NotificationRecipient::forUserId($uid, $user);
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
