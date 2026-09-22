<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Functional;

use Drupal\Tests\openintranet_notifications\Browser\ProfileModuleDiscoveryTrait;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests account page shells with the Open Intranet frontend theme.
 */
#[Group('openintranet_notifications')]
#[RunTestsInSeparateProcesses]
final class AccountPageThemeTest extends BrowserTestBase {

  use ProfileModuleDiscoveryTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'openintranet_notifications',
  ];

  /**
   * {@inheritdoc}
   */
  protected $apcuEnsureUniquePrefix = TRUE;

  /**
   * Optional profile block config includes third-party plugin settings.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'openintranet_theme';

  /**
   * Tests the shared shell, heading, tabs, and panel contract.
   */
  public function testAccountPageShells(): void {
    $account = $this->drupalCreateUser([
      'access user profiles',
      'administer own notification preferences',
    ]);
    $this->drupalLogin($account);

    $this->assertAccountPage(
      'notifications',
      '.account-page--inbox',
      'Notifications',
      FALSE,
    );
    $this->assertAccountPage(
      'user/' . $account->id() . '/notifications',
      '.account-page--preferences',
      'Notification preferences',
      TRUE,
    );
    $this->assertAccountPage(
      'user/' . $account->id() . '/edit',
      '.account-page--edit',
      'Edit profile',
      TRUE,
    );
    $this->assertAccountPage(
      'user/' . $account->id(),
      '.account-page--overview',
      $account->getDisplayName(),
      TRUE,
    );
  }

  /**
   * Asserts the shared page contract for one route.
   */
  private function assertAccountPage(
    string $path,
    string $variant_selector,
    string $heading,
    bool $has_tabs,
  ): void {
    $this->drupalGet($path);

    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    $assert->elementExists('css', '.account-page' . $variant_selector);
    $assert->elementsCount('css', 'h1', 1);
    $assert->elementTextEquals('css', 'h1', $heading);

    if ($has_tabs) {
      $assert->elementExists('css', '.account-page__tabs');
    }
    else {
      $assert->elementNotExists('css', '.account-page__tabs');
    }

  }

}
