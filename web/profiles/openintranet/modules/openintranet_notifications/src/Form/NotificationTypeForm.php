<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Form;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_notifications\Channel\ChannelPluginManager;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Policy\DeliveryPolicyManager;
use Drupal\openintranet_notifications\Renderer\TemplateRendererManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add/edit form for the notification_type config entity (§4.1, Chunk 4A).
 */
final class NotificationTypeForm extends EntityForm {

  public function __construct(
    private readonly ChannelPluginManager $channelManager,
    private readonly DeliveryPolicyManager $policyManager,
    private readonly TemplateRendererManager $rendererManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('plugin.manager.openintranet_notification_channel'),
      $container->get('plugin.manager.notification_delivery_policy'),
      $container->get('plugin.manager.notification_template_renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\openintranet_notifications\Entity\NotificationTypeInterface $type */
    $type = $this->entity;
    $channel_options = $this->channelOptions();

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $type->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $type->id(),
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'source' => ['label'],
      ],
      '#disabled' => !$type->isNew(),
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $type->getDescription(),
      '#rows' => 2,
    ];
    $form['category'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Category'),
      '#description' => $this->t('A grouping key used by the admin UI (e.g. social, system).'),
      '#default_value' => $type->getCategory(),
    ];
    $form['default_priority'] = [
      '#type' => 'select',
      '#title' => $this->t('Default priority'),
      '#options' => [
        'low' => $this->t('Low'),
        'normal' => $this->t('Normal'),
        'high' => $this->t('High'),
        'urgent' => $this->t('Urgent'),
      ],
      '#default_value' => $type->getDefaultPriority(),
      '#required' => TRUE,
    ];
    $form['default_channels'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Default channels'),
      '#description' => $this->t('Channels selected by default for this type.'),
      '#options' => $channel_options,
      '#default_value' => $type->getDefaultChannels(),
    ];
    $form['forced_channels'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Forced channels'),
      '#description' => $this->t('Channels the user cannot opt out of.'),
      '#options' => $channel_options,
      '#default_value' => $type->getForcedChannels(),
    ];
    $form['template_renderer'] = [
      '#type' => 'select',
      '#title' => $this->t('Template renderer'),
      '#options' => $this->pluginOptions($this->rendererManager->getDefinitions()),
      '#default_value' => $type->getTemplateRenderer(),
      '#required' => TRUE,
    ];
    $form['subject_template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Subject template'),
      '#default_value' => $type->getSubjectTemplate(),
      '#rows' => 2,
    ];
    $form['body_template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body template'),
      '#default_value' => $type->getBodyTemplate(),
      '#rows' => 4,
    ];
    $form['summary_template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Summary template'),
      '#default_value' => $type->getSummaryTemplate(),
      '#rows' => 2,
    ];
    $form['delivery_policy'] = [
      '#type' => 'select',
      '#title' => $this->t('Delivery policy'),
      '#options' => $this->pluginOptions($this->policyManager->getDefinitions()),
      '#default_value' => $type->getDeliveryPolicy(),
      '#required' => TRUE,
    ];
    $form['recipient_resolvers'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Recipient resolvers'),
      '#description' => $this->t('A YAML list of resolver definitions, each a mapping of <code>id</code> and optional <code>configuration</code>. Example:<br><code>- id: entity_author<br>&nbsp;&nbsp;configuration:<br>&nbsp;&nbsp;&nbsp;&nbsp;entity_key: commented_entity</code>'),
      '#default_value' => $this->resolversToYaml($type->getRecipientResolvers()),
      '#rows' => 6,
    ];
    $form['dedupe_window'] = [
      '#type' => 'number',
      '#title' => $this->t('Dedupe window (seconds)'),
      '#min' => 0,
      '#default_value' => $type->getDedupeWindow(),
    ];
    $form['rate_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Rate limit (per user)'),
      '#description' => $this->t('Maximum notifications of this type a single user may receive within the rate-limit window. 0 disables the cap.'),
      '#min' => 0,
      '#default_value' => $type->getRateLimit(),
    ];
    $form['rate_limit_window'] = [
      '#type' => 'number',
      '#title' => $this->t('Rate-limit window (seconds)'),
      '#description' => $this->t('The rolling window the rate limit applies over.'),
      '#min' => 1,
      '#default_value' => $type->getRateLimitWindow(),
    ];
    $form['user_can_override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Users can override the default channel selection'),
      '#default_value' => $type->userCanOverride(),
    ];
    $form['audit_retention_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Audit retention (days)'),
      '#description' => $this->t('0 falls back to the global default retention.'),
      '#min' => 0,
      '#default_value' => $type->getAuditRetentionDays(),
    ];
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $type->isEnabled(),
    ];

    return $form;
  }

  /**
   * Machine-name existence callback.
   *
   * @param string $id
   *   The machine name to check.
   *
   * @return bool
   *   TRUE when a notification type with this id already exists.
   */
  public function exists(string $id): bool {
    return (bool) $this->entityTypeManager
      ->getStorage('openintranet_notification_type')
      ->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $resolvers = $this->parseResolvers($form_state->getValue('recipient_resolvers'));
    if ($resolvers === FALSE) {
      $form_state->setErrorByName(
        'recipient_resolvers',
        $this->t('Recipient resolvers must be valid YAML: a list of mappings, each with an <code>id</code> key.'),
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state): void {
    if (!$entity instanceof NotificationTypeInterface) {
      return;
    }
    // Pull the structured fields out of the values so the default copy does not
    // assign the raw checkbox/textarea shapes onto the typed entity properties,
    // and read the resolver value before this copy normalizes it.
    $values = $form_state->getValues();
    unset($values['default_channels'], $values['forced_channels'], $values['recipient_resolvers']);
    foreach ($values as $key => $value) {
      $entity->set($key, $value);
    }

    $entity->set('default_channels', array_values(array_filter((array) $form_state->getValue('default_channels'))));
    $entity->set('forced_channels', array_values(array_filter((array) $form_state->getValue('forced_channels'))));
    $resolvers = $this->parseResolvers($form_state->getValue('recipient_resolvers'));
    $entity->set('recipient_resolvers', $resolvers === FALSE ? [] : $resolvers);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\openintranet_notifications\Entity\NotificationType $type */
    $type = $this->entity;

    $status = $type->save();

    $this->messenger()->addStatus($status === SAVED_NEW
      ? $this->t('Created the %label notification type.', ['%label' => $type->label()])
      : $this->t('Updated the %label notification type.', ['%label' => $type->label()]));

    $form_state->setRedirectUrl($type->toUrl('collection'));

    return $status;
  }

  /**
   * Builds the channel checkbox options from the channel plugin manager.
   *
   * @return array<string, string>
   *   Channel id => label.
   */
  private function channelOptions(): array {
    return $this->pluginOptions($this->channelManager->getSelectableDefinitions());
  }

  /**
   * Maps plugin definitions to id => label options.
   *
   * @param array<string, array<string, mixed>> $definitions
   *   Plugin definitions keyed by id.
   *
   * @return array<string, string>
   *   Plugin id => human-readable label.
   */
  private function pluginOptions(array $definitions): array {
    $options = [];
    foreach ($definitions as $id => $definition) {
      $options[$id] = (string) ($definition['label'] ?? $id);
    }
    asort($options);
    return $options;
  }

  /**
   * Parses the resolver textarea value into the stored structure.
   *
   * The raw YAML string is re-parsed independently in each phase (validateForm
   * decodes it to report errors; copyFormValuesToEntity decodes it again to
   * store the normalized list) — the decoded value is never written back to the
   * form state. This is safe because the method is idempotent: it accepts the
   * raw YAML string or an already-decoded array and yields the same result.
   *
   * @param mixed $value
   *   The raw form value: a YAML string or an already-decoded array.
   *
   * @return array<int, array{id: string, configuration: array<string, mixed>}>|false
   *   The normalized resolver list, or FALSE when the value is invalid.
   */
  private function parseResolvers(mixed $value): array|false {
    if (is_string($value)) {
      $value = trim($value);
      if ($value === '') {
        return [];
      }
      try {
        $parsed = Yaml::decode($value);
      }
      catch (InvalidDataTypeException) {
        return FALSE;
      }
    }
    else {
      $parsed = $value;
    }

    if ($parsed === [] || $parsed === NULL) {
      return [];
    }
    if (!is_array($parsed) || !array_is_list($parsed)) {
      return FALSE;
    }

    $resolvers = [];
    foreach ($parsed as $item) {
      if (!is_array($item) || !isset($item['id']) || !is_string($item['id'])) {
        return FALSE;
      }
      $configuration = $item['configuration'] ?? [];
      if (!is_array($configuration)) {
        return FALSE;
      }
      $resolvers[] = ['id' => $item['id'], 'configuration' => $configuration];
    }

    return $resolvers;
  }

  /**
   * Serializes the stored resolver list back to YAML for the textarea.
   *
   * @param array<int, array{id: string, configuration: array<string, mixed>}> $resolvers
   *   The stored resolver list.
   *
   * @return string
   *   The YAML representation, or an empty string when there are none.
   */
  private function resolversToYaml(array $resolvers): string {
    return $resolvers === [] ? '' : Yaml::encode($resolvers);
  }

}
