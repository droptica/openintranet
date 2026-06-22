<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Service\DeliveryQueue;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Cancels a notification and its still-pending deliveries.
 *
 * Sets the notification status to cancelled and cancels every delivery that
 * has not yet reached a terminal state (pending/processing), so the worker
 * skips them. Already-sent/delivered/cancelled rows are left untouched.
 */
#[Action(
  id: 'openintranet_notifications_cancel',
  label: new TranslatableMarkup('Notification: cancel'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Cancel a notification and its pending/processing deliveries.'),
  version_introduced: '1.0.0',
)]
final class Cancel extends ConfigurableActionBase {

  use NotificationActionTrait;

  /**
   * The delivery statuses that are still cancellable.
   */
  private const CANCELLABLE_STATUSES = ['pending', 'processing'];

  /**
   * The delivery queue service (owns the shared per-delivery cancel logic).
   *
   * @var \Drupal\openintranet_notifications\Service\DeliveryQueue
   */
  protected DeliveryQueue $deliveryQueue;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->deliveryQueue = $container->get('openintranet_notifications.delivery_queue');
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

    $notification->set('status', 'cancelled');
    $notification->save();

    $deliveries = $this->entityTypeManager
      ->getStorage('openintranet_notif_delivery')
      ->loadByProperties(['notification_id' => $notification->id()]);
    /** @var \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $delivery */
    foreach ($deliveries as $delivery) {
      if (\in_array($delivery->get('status')->value, self::CANCELLABLE_STATUSES, TRUE)) {
        $this->deliveryQueue->cancel($delivery);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'notification' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['notification'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Notification'),
      '#description' => $this->t('A token resolving to the notification entity to cancel.'),
      '#default_value' => $this->configuration['notification'],
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
    parent::submitConfigurationForm($form, $form_state);
  }

}
