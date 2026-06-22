<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_notifications\Channel\ChannelPluginManager;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Policy\DeliveryPolicyManager;
use Drupal\openintranet_notifications\Service\NotificationDispatcher;
use Drupal\openintranet_notifications\Service\NotificationFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test/preview/dry-run send form for a notification type (Chunk 4D).
 *
 * Dry-run (default) renders the message and resolves the channels the type's
 * delivery policy would select WITHOUT persisting or enqueuing anything; a live
 * submit hands off to the dispatcher, which owns the real send logic (§7 DRY).
 */
final class NotificationTestForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly NotificationFactory $notificationFactory,
    private readonly NotificationDispatcher $notificationDispatcher,
    private readonly DeliveryPolicyManager $policyManager,
    private readonly ChannelPluginManager $channelManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('openintranet_notifications.notification_factory'),
      $container->get('openintranet_notifications.notification_dispatcher'),
      $container->get('plugin.manager.notification_delivery_policy'),
      $container->get('plugin.manager.notification_channel'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openintranet_notifications_test';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['notification_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Notification type'),
      '#options' => $this->typeOptions(),
      '#required' => TRUE,
    ];
    $form['uid'] = [
      '#type' => 'number',
      '#title' => $this->t('Recipient user id'),
      '#description' => $this->t('The uid of the user to notify.'),
      '#min' => 1,
      '#required' => TRUE,
    ];
    $form['channel'] = [
      '#type' => 'select',
      '#title' => $this->t('Channel override'),
      '#description' => $this->t('Optional: preview a single channel instead of the resolved set. Applies to the dry-run preview only; a live send always uses the resolved channels.'),
      '#options' => ['' => $this->t('- None -')] + $this->channelOptions(),
      // The override only affects the dry-run preview, so disable it for a live
      // send to stop it reading as a control over real dispatch (§review #3).
      '#states' => [
        'enabled' => [':input[name="dry_run"]' => ['checked' => TRUE]],
      ],
    ];
    $form['dry_run'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Dry run'),
      '#description' => $this->t('Preview the rendered message and the channels that would be used, without creating or sending anything.'),
      '#default_value' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $typeId = (string) $form_state->getValue('notification_type');
    $uid = (int) $form_state->getValue('uid');
    $channelOverride = (string) $form_state->getValue('channel');

    if ((bool) $form_state->getValue('dry_run')) {
      $preview = $this->preview($typeId, $uid, $channelOverride);
      $form_state->set('preview', $preview);
      $this->messenger()->addStatus($this->t('Dry run: %subject — channels: %channels (nothing was created or sent).', [
        '%subject' => $preview['subject'],
        '%channels' => $preview['channels'] === [] ? $this->t('none') : implode(', ', $preview['channels']),
      ]));
      return;
    }

    $this->notificationDispatcher->dispatchRequest($typeId, [$uid], []);
    $this->messenger()->addStatus($this->t('Dispatched a %type notification to user %uid.', [
      '%type' => $typeId,
      '%uid' => $uid,
    ]));
  }

  /**
   * Builds a non-persisted preview of the message and selected channels.
   *
   * @param string $typeId
   *   The notification_type id.
   * @param int $uid
   *   The recipient user id.
   * @param string $channelOverride
   *   A single channel id to preview, or '' to resolve via the policy.
   *
   * @return array{subject: string, body: string, summary: string, channels: array<int, string>}
   *   The rendered message parts and the channel ids that would be used.
   */
  private function preview(string $typeId, int $uid, string $channelOverride): array {
    /** @var \Drupal\user\UserInterface|null $account */
    $account = $this->entityTypeManager->getStorage('user')->load($uid);

    // Render the message without saving: the factory renders the type's
    // templates and exposes [user:*] tokens via recipient_account.
    $notification = $this->notificationFactory->create($typeId, [
      'uid' => $uid,
      'recipient_account' => $account,
    ]);

    $channels = $channelOverride !== ''
      ? [$channelOverride]
      : $this->resolveChannels($typeId, $uid, $account);

    return [
      'subject' => (string) $notification->get('subject')->value,
      'body' => (string) $notification->get('body')->value,
      'summary' => (string) $notification->get('summary')->value,
      'channels' => $channels,
    ];
  }

  /**
   * Resolves the channels the type's delivery policy would select.
   *
   * @param string $typeId
   *   The notification_type id.
   * @param int $uid
   *   The recipient user id.
   * @param \Drupal\user\UserInterface|null $account
   *   The loaded recipient account, if any.
   *
   * @return array<int, string>
   *   The selected channel ids.
   */
  private function resolveChannels(string $typeId, int $uid, ?object $account): array {
    /** @var \Drupal\openintranet_notifications\Entity\NotificationTypeInterface|null $type */
    $type = $this->entityTypeManager
      ->getStorage('openintranet_notification_type')
      ->load($typeId);
    if ($type === NULL) {
      return [];
    }

    $recipient = new NotificationRecipient(
      type: 'user',
      id: $uid,
      account: $account,
    );
    $policyId = $type->getDeliveryPolicy() ?: 'user_preferences';
    $policy = $this->policyManager->createInstance($policyId);
    return $policy->selectChannels($type, $recipient, []);
  }

  /**
   * Builds the notification-type select options.
   *
   * @return array<string, string>
   *   Type id => label.
   */
  private function typeOptions(): array {
    $options = [];
    /** @var \Drupal\openintranet_notifications\Entity\NotificationTypeInterface $type */
    foreach ($this->entityTypeManager->getStorage('openintranet_notification_type')->loadMultiple() as $type) {
      $options[$type->id()] = $type->label();
    }
    asort($options);
    return $options;
  }

  /**
   * Builds the channel-override select options.
   *
   * @return array<string, string>
   *   Channel id => label.
   */
  private function channelOptions(): array {
    $options = [];
    foreach ($this->channelManager->getDefinitions() as $id => $definition) {
      $options[$id] = (string) ($definition['label'] ?? $id);
    }
    asort($options);
    return $options;
  }

}
