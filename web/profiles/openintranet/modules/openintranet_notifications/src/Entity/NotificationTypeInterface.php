<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for the notification_type config entity.
 *
 * A notification type is a recipe-deployable routing profile: it declares the
 * default/forced channels, the recipient resolvers, the rendering templates,
 * the delivery policy and the dedupe/audit knobs for one kind of notification
 * (00-synteza §4.1).
 */
interface NotificationTypeInterface extends ConfigEntityInterface {

  /**
   * Gets the human-readable description.
   */
  public function getDescription(): string;

  /**
   * Gets the grouping category used by the admin UI.
   */
  public function getCategory(): string;

  /**
   * Gets the default priority (low|normal|high|urgent).
   */
  public function getDefaultPriority(): string;

  /**
   * Gets the channel ids selected by default.
   *
   * @return string[]
   *   The default channel plugin ids.
   */
  public function getDefaultChannels(): array;

  /**
   * Gets the channel ids the user cannot opt out of.
   *
   * @return string[]
   *   The forced channel plugin ids.
   */
  public function getForcedChannels(): array;

  /**
   * Gets the recipient resolver configurations.
   *
   * @return array<int, array{id: string, configuration: array<string, mixed>}>
   *   A list of {id, configuration} resolver definitions.
   */
  public function getRecipientResolvers(): array;

  /**
   * Gets the per-channel template map.
   *
   * @return array<string, string>
   *   A map of channel id to renderer template name.
   */
  public function getTemplateMap(): array;

  /**
   * Gets the template renderer plugin id (defaults to token_text).
   */
  public function getTemplateRenderer(): string;

  /**
   * Gets the subject token template.
   */
  public function getSubjectTemplate(): string;

  /**
   * Gets the body token template.
   */
  public function getBodyTemplate(): string;

  /**
   * Gets the summary token template.
   */
  public function getSummaryTemplate(): string;

  /**
   * Gets the delivery policy plugin id.
   */
  public function getDeliveryPolicy(): string;

  /**
   * Gets the dedupe window in seconds.
   */
  public function getDedupeWindow(): int;

  /**
   * Gets the per-(user, type) rate limit; 0 disables the cap (00-synteza §8).
   *
   * @return int
   *   The maximum notifications a single user may receive for this type within
   *   the rate-limit window. A non-positive value means no limit.
   */
  public function getRateLimit(): int;

  /**
   * Gets the rate-limit window in seconds (paired with the rate limit).
   */
  public function getRateLimitWindow(): int;

  /**
   * Whether users may override the default channel selection.
   */
  public function userCanOverride(): bool;

  /**
   * Gets how many days delivery audit records are retained.
   */
  public function getAuditRetentionDays(): int;

}
