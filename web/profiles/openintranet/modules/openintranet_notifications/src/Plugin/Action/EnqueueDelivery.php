<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Service\NotificationDispatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Enqueues channel deliveries for an already-created notification.
 *
 * The atomic counterpart of the enqueue step in create_and_enqueue: it loads
 * the notification from a token and runs it through the dispatcher's enqueue
 * path (dedupe/rate guards, policy channel selection, delivery rows + queue).
 */
#[Action(
  id: 'openintranet_notifications_enqueue_delivery',
  label: new TranslatableMarkup('Notification: enqueue delivery'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Enqueue channel deliveries for an already-created notification loaded from a token.'),
  version_introduced: '1.0.0',
)]
final class EnqueueDelivery extends ConfigurableActionBase {

  use NotificationActionTrait;

  /**
   * The notification dispatcher.
   *
   * @var \Drupal\openintranet_notifications\Service\NotificationDispatcher
   */
  protected NotificationDispatcher $dispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->dispatcher = $container->get('openintranet_notifications.notification_dispatcher');
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

    $this->dispatcher->enqueue($notification);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'notification' => '',
      'recipients' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['notification'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Notification'),
      '#description' => $this->t('A token resolving to the notification entity to enqueue.'),
      '#default_value' => $this->configuration['notification'],
      '#eca_token_replacement' => TRUE,
      '#required' => TRUE,
    ];
    $form['recipients'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recipients'),
      '#description' => $this->t('Optional override token; by default the notification own recipient is used.'),
      '#default_value' => $this->configuration['recipients'],
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['notification'] = $form_state->getValue('notification');
    $this->configuration['recipients'] = $form_state->getValue('recipients');
    parent::submitConfigurationForm($form, $form_state);
  }

}
