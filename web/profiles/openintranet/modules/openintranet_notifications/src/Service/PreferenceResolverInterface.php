<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\openintranet_notifications\Entity\UserNotificationSettingsInterface;

/**
 * Resolves a user's notification preferences.
 *
 * The single read path for per-user channel preferences and quiet hours; the
 * delivery policy depends on this interface so the backing storage can change
 * without touching plugins (00-synteza §4.4).
 */
interface PreferenceResolverInterface {

  /**
   * Whether the given channel is enabled for the user and notification type.
   *
   * Reads the user's stored preference; falls back to the global
   * default_user_preferences when the user has no explicit setting.
   *
   * @param int $uid
   *   The user id.
   * @param string $type
   *   The notification type id.
   * @param string $channel
   *   The channel plugin id.
   *
   * @return bool
   *   TRUE when the channel is enabled for that user and type.
   */
  public function isEnabled(int $uid, string $type, string $channel): bool;

  /**
   * Gets the user's quiet-hours window, or NULL when none is configured.
   *
   * @param int $uid
   *   The user id.
   *
   * @return array{start: string, end: string, tz: ?string}|null
   *   The quiet-hours window, or NULL.
   */
  public function getQuietHours(int $uid): ?array;

  /**
   * Loads the user's settings entity, creating and saving it on first access.
   *
   * @param int $uid
   *   The user id.
   *
   * @return \Drupal\openintranet_notifications\Entity\UserNotificationSettingsInterface
   *   The persisted settings entity.
   */
  public function loadOrCreateFor(int $uid): UserNotificationSettingsInterface;

}
