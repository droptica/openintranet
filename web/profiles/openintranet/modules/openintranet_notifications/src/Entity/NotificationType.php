<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Entity\Handler\NotificationTypeListBuilder;
use Drupal\openintranet_notifications\Form\NotificationTypeDeleteForm;
use Drupal\openintranet_notifications\Form\NotificationTypeForm;

/**
 * Defines the notification_type config entity.
 *
 * Routing profile + template for one kind of notification (00-synteza §4.1).
 * Channel/policy/resolver ids are stored as plain strings; the entity does not
 * validate plugin existence (that is the dispatcher's concern at send time).
 */
#[ConfigEntityType(
  id: 'openintranet_notification_type',
  label: new TranslatableMarkup('Notification type'),
  label_collection: new TranslatableMarkup('Notification types'),
  label_singular: new TranslatableMarkup('notification type'),
  label_plural: new TranslatableMarkup('notification types'),
  config_prefix: 'openintranet_notification_type',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'status' => 'status',
  ],
  admin_permission: 'administer notification types',
  handlers: [
    'list_builder' => NotificationTypeListBuilder::class,
    'form' => [
      'add' => NotificationTypeForm::class,
      'edit' => NotificationTypeForm::class,
      'delete' => NotificationTypeDeleteForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/config/openintranet/notifications/types',
    'add-form' => '/admin/config/openintranet/notifications/types/add',
    'edit-form' => '/admin/config/openintranet/notifications/types/{openintranet_notification_type}',
    'delete-form' => '/admin/config/openintranet/notifications/types/{openintranet_notification_type}/delete',
  ],
  label_count: [
    'singular' => '@count notification type',
    'plural' => '@count notification types',
  ],
  config_export: [
    'id',
    'label',
    'description',
    'category',
    'default_priority',
    'default_channels',
    'forced_channels',
    'recipient_resolvers',
    'template_map',
    'template_renderer',
    'subject_template',
    'body_template',
    'summary_template',
    'delivery_policy',
    'dedupe_window',
    'rate_limit',
    'rate_limit_window',
    'user_can_override',
    'audit_retention_days',
    'status',
  ],
)]
final class NotificationType extends ConfigEntityBase implements NotificationTypeInterface {

  /**
   * The machine name.
   */
  protected string $id;

  /**
   * The human-readable label.
   */
  protected string $label;

  /**
   * The human-readable description.
   */
  protected string $description = '';

  /**
   * The grouping category used by the admin UI.
   */
  protected string $category = '';

  /**
   * The default priority (low|normal|high|urgent).
   */
  protected string $default_priority = 'normal';

  /**
   * The channel ids selected by default.
   *
   * @var string[]
   */
  protected array $default_channels = [];

  /**
   * The channel ids the user cannot opt out of.
   *
   * @var string[]
   */
  protected array $forced_channels = [];

  /**
   * The recipient resolver configurations.
   *
   * @var array<int, array{id: string, configuration: array<string, mixed>}>
   */
  protected array $recipient_resolvers = [];

  /**
   * The per-channel template map (channel id => body template).
   *
   * Wired at send time (00-synteza §4.1): DeliverySender::buildMessage()
   * renders the mapped template via NotificationFactory::renderForChannel() for
   * a channel with an entry here, overriding the channel-agnostic stored body.
   * A channel with no entry sends the stored default.
   *
   * @var array<string, string>
   */
  protected array $template_map = [];

  /**
   * The template renderer plugin id.
   */
  protected string $template_renderer = 'token_text';

  /**
   * The subject token template.
   */
  protected string $subject_template = '';

  /**
   * The body token template.
   */
  protected string $body_template = '';

  /**
   * The summary token template.
   */
  protected string $summary_template = '';

  /**
   * The delivery policy plugin id.
   */
  protected string $delivery_policy = 'user_preferences';

  /**
   * The dedupe window in seconds.
   */
  protected int $dedupe_window = 0;

  /**
   * The per-(user, type) rate limit; 0 disables the cap (00-synteza §8).
   */
  protected int $rate_limit = 0;

  /**
   * The rate-limit window in seconds (paired with rate_limit).
   */
  protected int $rate_limit_window = 3600;

  /**
   * Whether users may override the default channel selection.
   */
  protected bool $user_can_override = TRUE;

  /**
   * How many days delivery audit records are retained.
   */
  protected int $audit_retention_days = 0;

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getCategory(): string {
    return $this->category;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultPriority(): string {
    return $this->default_priority;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultChannels(): array {
    return $this->default_channels;
  }

  /**
   * {@inheritdoc}
   */
  public function getForcedChannels(): array {
    return $this->forced_channels;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientResolvers(): array {
    return $this->recipient_resolvers;
  }

  /**
   * {@inheritdoc}
   */
  public function getTemplateMap(): array {
    return $this->template_map;
  }

  /**
   * {@inheritdoc}
   */
  public function getTemplateRenderer(): string {
    return $this->template_renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubjectTemplate(): string {
    return $this->subject_template;
  }

  /**
   * {@inheritdoc}
   */
  public function getBodyTemplate(): string {
    return $this->body_template;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummaryTemplate(): string {
    return $this->summary_template;
  }

  /**
   * {@inheritdoc}
   */
  public function getDeliveryPolicy(): string {
    return $this->delivery_policy;
  }

  /**
   * {@inheritdoc}
   */
  public function getDedupeWindow(): int {
    return $this->dedupe_window;
  }

  /**
   * {@inheritdoc}
   */
  public function getRateLimit(): int {
    return $this->rate_limit;
  }

  /**
   * {@inheritdoc}
   */
  public function getRateLimitWindow(): int {
    return $this->rate_limit_window;
  }

  /**
   * {@inheritdoc}
   */
  public function userCanOverride(): bool {
    return $this->user_can_override;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuditRetentionDays(): int {
    return $this->audit_retention_days;
  }

}
