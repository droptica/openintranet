<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for the user_notification_settings content entity.
 *
 * One row per user (1:1): the per-type/channel preference matrix plus quiet
 * hours and a language override (00-synteza §4.4). Accessed via the
 * preference_resolver so the storage can change without touching plugins.
 */
interface UserNotificationSettingsInterface extends ContentEntityInterface {

  /**
   * Gets the per-type/channel preference matrix.
   *
   * @return array<string, array<string, bool>>
   *   A map of notification type id to a map of channel id to enabled flag.
   */
  public function getPreferences(): array;

  /**
   * Gets the quiet-hours window, or NULL when none is configured.
   *
   * @return array{start: string, end: string, tz: ?string}|null
   *   The start/end HH:MM strings and timezone, or NULL.
   */
  public function getQuietHours(): ?array;

}
