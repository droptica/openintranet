<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\openintranet_notifications\Entity\Notification;
use Drupal\Tests\openintranet_notifications\Browser\ProfileModuleDiscoveryTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use WebDriver\Key;

/**
 * Tests the notification bell dropdown JavaScript behavior.
 */
#[Group('openintranet_notifications')]
#[RunTestsInSeparateProcesses]
final class NotificationBellTest extends WebDriverTestBase {

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
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests toggle, outside-click, and Escape closing behavior.
   */
  public function testBellDropdownInteractions(): void {
    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);

    Notification::create([
      'type' => 'default',
      'uid' => $account->id(),
      'subject' => 'Browser notification',
      'body' => 'Browser notification body',
      'priority' => 'normal',
      'status' => 'delivered',
    ])->save();

    $this->drupalPlaceBlock('openintranet_notification_bell', [
      'id' => 'notification_bell_test',
      'region' => 'header',
      'label_display' => FALSE,
    ]);
    $this->drupalGet('user/' . $account->id());

    $assert = $this->assertSession();
    $bell = $assert->elementExists('css', '.notification-bell');
    $toggle = $assert->elementExists('css', '.notification-bell__toggle');
    $dropdown = $assert->elementExists('css', '.notification-bell__dropdown');

    self::assertFalse($bell->hasClass('is-open'));
    self::assertSame('false', $toggle->getAttribute('aria-expanded'));
    self::assertFalse($dropdown->isVisible());

    // A click opens the dropdown and updates its accessible state.
    $toggle->click();
    $assert->waitForElementVisible('css', '.notification-bell.is-open .notification-bell__dropdown');
    self::assertTrue($bell->hasClass('is-open'));
    self::assertSame('true', $toggle->getAttribute('aria-expanded'));

    // A second toggle click closes it.
    $toggle->click();
    $assert->waitForElement('css', '.notification-bell:not(.is-open)');
    self::assertSame('false', $toggle->getAttribute('aria-expanded'));
    self::assertFalse($dropdown->isVisible());

    // A click outside the bell closes an open dropdown.
    $toggle->click();
    $assert->waitForElementVisible('css', '.notification-bell.is-open .notification-bell__dropdown');
    $outside = $assert->elementExists('css', 'main');
    $outside->click();
    $assert->waitForElement('css', '.notification-bell:not(.is-open)');
    self::assertSame('false', $toggle->getAttribute('aria-expanded'));
    self::assertFalse($dropdown->isVisible());

    // Escape closes the dropdown from keyboard interaction.
    $toggle->click();
    $assert->waitForElementVisible('css', '.notification-bell.is-open .notification-bell__dropdown');
    $toggle->focus();
    $toggle->keyDown(Key::ESCAPE);
    $toggle->keyUp(Key::ESCAPE);
    $assert->waitForElement('css', '.notification-bell:not(.is-open)');
    self::assertSame('false', $toggle->getAttribute('aria-expanded'));
    self::assertFalse($dropdown->isVisible());
  }

}
