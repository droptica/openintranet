<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\openintranet_notifications\Service\NotificationFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds and saves a single notification, without enqueuing it.
 *
 * The atomic counterpart of create_and_enqueue: it stops after persisting the
 * notification and writes the new id to an output token so a model can act on
 * it (e.g. enqueue it later or set further fields).
 */
#[Action(
  id: 'openintranet_notifications_create',
  label: new TranslatableMarkup('Notification: create'),
  type: 'entity',
)]
#[EcaAction(
  description: new TranslatableMarkup('Build and save a single notification for one recipient, writing its id to a token.'),
  version_introduced: '1.0.0',
)]
final class CreateNotification extends ConfigurableActionBase {

  use NotificationActionTrait;

  /**
   * The notification factory.
   *
   * @var \Drupal\openintranet_notifications\Service\NotificationFactory
   */
  protected NotificationFactory $factory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->factory = $container->get('openintranet_notifications.notification_factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $typeId = trim((string) $this->tokenService->replaceClear($this->configuration['notification_type']));
    $uids = $this->resolveRecipientUids((string) $this->configuration['uid']);
    $uid = $uids[0] ?? 0;
    if ($typeId === '' || $uid === 0) {
      return;
    }

    $values = ['uid' => $uid];
    if ($object instanceof EntityInterface) {
      $values['source_entity'] = $object;
    }
    $subject = (string) $this->tokenService->replaceClear($this->configuration['subject']);
    $body = (string) $this->tokenService->replaceClear($this->configuration['body']);
    if ($subject !== '') {
      $values['subject'] = $subject;
    }
    if ($body !== '') {
      $values['body'] = $body;
    }

    $notification = $this->factory->create($typeId, $values);
    $notification->save();

    $tokenName = trim((string) $this->configuration['token_name']);
    if ($tokenName !== '') {
      $this->tokenService->addTokenData($tokenName, (string) $notification->id());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'notification_type' => '',
      'uid' => '',
      'subject' => '',
      'body' => '',
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['notification_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Notification type'),
      '#default_value' => $this->configuration['notification_type'],
      '#eca_token_replacement' => TRUE,
      '#required' => TRUE,
    ];
    $form['uid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recipient user id'),
      '#description' => $this->t('A token resolving to the recipient user id.'),
      '#default_value' => $this->configuration['uid'],
      '#eca_token_replacement' => TRUE,
      '#required' => TRUE,
    ];
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#description' => $this->t('Optional explicit subject; leave empty to render from the type template.'),
      '#default_value' => $this->configuration['subject'],
      '#eca_token_replacement' => TRUE,
    ];
    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#description' => $this->t('Optional explicit body; leave empty to render from the type template.'),
      '#default_value' => $this->configuration['body'],
      '#eca_token_replacement' => TRUE,
    ];
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Output token name'),
      '#description' => $this->t('The name of the token to store the new notification id.'),
      '#default_value' => $this->configuration['token_name'],
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['notification_type'] = $form_state->getValue('notification_type');
    $this->configuration['uid'] = $form_state->getValue('uid');
    $this->configuration['subject'] = $form_state->getValue('subject');
    $this->configuration['body'] = $form_state->getValue('body');
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
