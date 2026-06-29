<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Event\NotificationEvents;
use Drupal\openintranet_notifications\Event\NotificationSeenEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Marks a notification seen and/or read.
 *
 * Stamps seen_at and/or read_at according to the mode and, when the seen state
 * is set, dispatches NotificationEvents::SEEN so downstream models (badge
 * counts, digests) can react.
 */
#[Action(
  id: 'openintranet_notifications_mark_seen_read',
  label: new TranslatableMarkup('Notification: mark seen/read'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Mark a notification seen, read, or both; fires the seen event when seen is set.'),
  version_introduced: '1.0.0',
)]
final class MarkSeenRead extends ConfigurableActionBase {

  use NotificationActionTrait;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->eventDispatcher = $container->get('event_dispatcher');
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

    $mode = (string) $this->configuration['mode'];
    $markSeen = $mode === 'seen' || $mode === 'both';
    $markRead = $mode === 'read' || $mode === 'both';
    if (!$markSeen && !$markRead) {
      return;
    }

    if ($markSeen) {
      $notification->setSeen();
    }
    if ($markRead) {
      $notification->setRead();
    }
    $notification->save();

    if ($markSeen) {
      $this->eventDispatcher->dispatch(new NotificationSeenEvent($notification), NotificationEvents::SEEN);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'notification' => '',
      'mode' => 'seen',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['notification'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Notification'),
      '#description' => $this->t('A token resolving to the notification entity to mark.'),
      '#default_value' => $this->configuration['notification'],
      '#eca_token_replacement' => TRUE,
      '#required' => TRUE,
    ];
    $form['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#options' => [
        'seen' => $this->t('Seen'),
        'read' => $this->t('Read'),
        'both' => $this->t('Both'),
      ],
      '#default_value' => $this->configuration['mode'],
      '#required' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['notification'] = $form_state->getValue('notification');
    $this->configuration['mode'] = $form_state->getValue('mode');
    parent::submitConfigurationForm($form, $form_state);
  }

}
