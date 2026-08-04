<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\openintranet_engagement\Service\OiEngagementTrackerInterface;

/**
 * Hooks for special events that aren't entity CRUD.
 */
final class SpecialEventHooks {

  /**
   * Constructs the SpecialEventHooks class.
   *
   * @param \Drupal\openintranet_engagement\Service\OiEngagementTrackerInterface $tracker
   *   The tracker service.
   */
  public function __construct(
    private readonly OiEngagementTrackerInterface $tracker,
  ) {}

  /**
   * Tracks user login.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The logged-in user account.
   */
  #[Hook('user_login')]
  public function onUserLogin(AccountInterface $account): void {
    $this->tracker->track('login', $account, [
      'value' => 1,
    ]);
  }

  /**
   * Tracks user logout.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The logged-out user account.
   */
  #[Hook('user_logout')]
  public function onUserLogout(AccountInterface $account): void {
    $this->tracker->track('logout', $account, [
      'value' => 0,
    ]);
  }

  /**
   * Tracks file downloads.
   *
   * @param string $uri
   *   The URI of the file being downloaded.
   *
   * @return array|null
   *   NULL to not affect the download, or access headers.
   */
  #[Hook('file_download')]
  public function onFileDownload(string $uri): ?array {
    // Track but don't interfere with download.
    $this->tracker->track('file_download', NULL, [
      'value' => 3,
      'data' => ['uri' => $uri],
    ]);

    // Return NULL to not affect the download.
    return NULL;
  }

}
