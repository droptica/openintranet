<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\openintranet_notifications\Entity\UserNotificationSettingsInterface;

/**
 * Default preference resolver backed by the user_notification_settings entity.
 */
final class PreferenceResolver implements PreferenceResolverInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isEnabled(int $uid, string $type, string $channel): bool {
    $settings = $this->load($uid);
    if ($settings !== NULL) {
      $preferences = $settings->getPreferences();
      if (isset($preferences[$type][$channel])) {
        return (bool) $preferences[$type][$channel];
      }
    }

    $defaults = $this->configFactory->get('openintranet_notifications.settings')
      ->get('default_user_preferences') ?? [];
    return (bool) ($defaults[$type][$channel] ?? FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuietHours(int $uid): ?array {
    return $this->load($uid)?->getQuietHours();
  }

  /**
   * {@inheritdoc}
   */
  public function loadOrCreateFor(int $uid): UserNotificationSettingsInterface {
    $settings = $this->load($uid);
    if ($settings !== NULL) {
      return $settings;
    }

    $storage = $this->entityTypeManager->getStorage('user_notification_settings');
    /** @var \Drupal\openintranet_notifications\Entity\UserNotificationSettingsInterface $settings */
    $settings = $storage->create(['uid' => $uid]);
    $settings->save();
    return $settings;
  }

  /**
   * Loads the settings entity for a user, or NULL when none exists yet.
   */
  private function load(int $uid): ?UserNotificationSettingsInterface {
    $storage = $this->entityTypeManager->getStorage('user_notification_settings');
    $matches = $storage->loadByProperties(['uid' => $uid]);
    $settings = reset($matches);
    return $settings instanceof UserNotificationSettingsInterface ? $settings : NULL;
  }

}
