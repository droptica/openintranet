<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Functional;

use Drupal\openintranet_notifications\Entity\Notification;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Entity\UserNotificationSettingsInterface;
use Drupal\Tests\openintranet_notifications\Browser\ProfileModuleDiscoveryTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the user-facing notification routes and forms in a browser.
 */
#[Group('openintranet_notifications')]
#[RunTestsInSeparateProcesses]
final class NotificationUiTest extends BrowserTestBase {

  use ProfileModuleDiscoveryTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
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
   * Tests notification routes, rendering, redirects, and settings workflows.
   *
   * The workflows share one minimal test-site installation to keep this
   * end-to-end suite focused and fast.
   */
  public function testNotificationUiWorkflows(): void {
    $this->assertRouteAccess();
    $this->assertInboxRenderingMarksOwnNotificationsRead();
    $this->assertNotificationViewMarksReadAndRedirects();
    $this->assertUserPreferencesFormSavesSettings();
    $this->assertNotificationSettingsFormSavesConfiguration();
  }

  /**
   * Tests anonymous denial and own-only preference access.
   */
  private function assertRouteAccess(): void {
    $owner = $this->drupalCreateUser();
    $other = $this->drupalCreateUser();
    $admin = $this->drupalCreateUser([
      'administer users',
    ]);

    $this->drupalGet('notifications');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('user/' . $owner->id() . '/notifications');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($owner);
    $this->drupalGet('notifications');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('user/' . $owner->id() . '/notifications');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'form.notifications-preferences');

    $this->drupalGet('user/' . $other->id() . '/notifications');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogout();
    $this->drupalLogin($admin);
    $this->drupalGet('user/' . $owner->id() . '/notifications');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests inbox rendering and its mark-read side effect.
   */
  private function assertInboxRenderingMarksOwnNotificationsRead(): void {
    $owner = $this->drupalCreateUser();
    $other = $this->drupalCreateUser();
    $actor = $this->drupalCreateUser();

    $first = $this->createNotification($owner, 'First own notification', 'First body', NULL, $actor);
    $second = $this->createNotification($owner, 'Second own notification');
    $this->createNotification($other, 'Other user secret');

    $this->drupalLogin($owner);
    $this->drupalGet('notifications');

    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('Notifications');
    $assert->pageTextContains('First own notification');
    $assert->pageTextContains('Second own notification');
    $assert->pageTextContains($actor->getDisplayName());
    $assert->pageTextContains('You are caught up on 2 new notifications.');
    $assert->pageTextNotContains('Other user secret');
    $assert->elementsCount('css', '.notifications-inbox__item', 2);
    $assert->elementsCount('css', '.notifications-inbox__item--unread', 0);
    $assert->elementsCount('css', '.notifications-inbox__time[datetime]', 2);
    $assert->elementExists('css', '.notifications-inbox__preferences');

    self::assertTrue($this->reloadNotification($first)->isRead());
    self::assertTrue($this->reloadNotification($first)->isSeen());
    self::assertTrue($this->reloadNotification($second)->isRead());
    self::assertTrue($this->reloadNotification($second)->isSeen());

    // The next request renders the persisted read state.
    $this->drupalGet('notifications');
    $assert->elementsCount('css', '.notifications-inbox__item--unread', 0);
    $assert->pageTextContains('You are all caught up.');
  }

  /**
   * Tests canonical notification access, rendering, and redirects.
   */
  private function assertNotificationViewMarksReadAndRedirects(): void {
    $owner = $this->drupalCreateUser();
    $other = $this->drupalCreateUser();

    $rendered = $this->createNotification(
      $owner,
      'Rendered notification',
      'Rendered notification body',
    );
    $redirected = $this->createNotification(
      $owner,
      'Redirected notification',
      '',
      '/user/' . $owner->id(),
    );

    $this->drupalLogin($owner);
    $this->drupalGet($rendered->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextEquals('css', 'h2', 'Rendered notification');
    $this->assertSession()->pageTextContains('Rendered notification body');
    self::assertTrue($this->reloadNotification($rendered)->isRead());

    $this->drupalGet($redirected->toUrl());
    $current_url = parse_url($this->getSession()->getCurrentUrl());
    self::assertIsArray($current_url);
    self::assertSame('/user/' . $owner->id(), $current_url['path']);
    self::assertTrue($this->reloadNotification($redirected)->isRead());

    $unsafe = $this->createNotification(
      $owner,
      'Unsafe redirect',
      '',
      'https://evil.example/phishing',
    );
    $this->drupalGet($unsafe->toUrl());
    $current_url = parse_url($this->getSession()->getCurrentUrl());
    self::assertIsArray($current_url);
    self::assertSame('/notifications', $current_url['path']);
    self::assertSame(parse_url($this->baseUrl, PHP_URL_HOST), $current_url['host']);
    self::assertTrue($this->reloadNotification($unsafe)->isRead());

    $private = $this->createNotification($owner, 'Private notification');
    $this->drupalLogout();
    $this->drupalLogin($other);
    $this->drupalGet($private->toUrl());
    $this->assertSession()->statusCodeEquals(403);
    self::assertFalse($this->reloadNotification($private)->isRead());

    $this->drupalLogout();
    $this->drupalGet($private->toUrl());
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests the rendered preference matrix and saved user preferences.
   */
  private function assertUserPreferencesFormSavesSettings(): void {
    $owner = $this->drupalCreateUser();
    $this->drupalLogin($owner);
    $this->drupalGet('user/' . $owner->id() . '/notifications');

    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('Default');
    $assert->pageTextContains('New comment');
    $assert->checkboxChecked('pref[default][inbox]');
    $assert->elementAttributeExists('css', '#edit-pref-default-inbox', 'disabled');

    $this->submitForm([
      'pref[default][email_core]' => FALSE,
      'pref[default][log_only]' => TRUE,
      'pref[new_comment][inbox]' => TRUE,
      'pref[new_comment][email_core]' => FALSE,
      'pref[new_comment][log_only]' => FALSE,
      'quiet_hours_start' => '22:00',
      'quiet_hours_end' => '06:30',
      'quiet_hours_tz' => 'Europe/Warsaw',
    ], 'Save preferences');

    $assert->statusMessageContains('Your notification preferences have been saved.', 'status');

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('user_notification_settings');
    $storage->resetCache();
    $matches = $storage->loadByProperties(['uid' => $owner->id()]);
    $settings = reset($matches);
    self::assertInstanceOf(UserNotificationSettingsInterface::class, $settings);

    $preferences = $settings->getPreferences();
    self::assertTrue($preferences['default']['inbox']);
    self::assertFalse($preferences['default']['email_core']);
    self::assertTrue($preferences['default']['log_only']);
    self::assertTrue($preferences['new_comment']['inbox']);

    self::assertSame([
      'start' => '22:00',
      'end' => '06:30',
      'tz' => 'Europe/Warsaw',
    ], $settings->getQuietHours());
  }

  /**
   * Tests global settings access, rendering, and persistence.
   */
  private function assertNotificationSettingsFormSavesConfiguration(): void {
    $this->drupalGet('admin/config/openintranet/notifications');
    $this->assertSession()->statusCodeEquals(403);

    $regular = $this->drupalCreateUser();
    $this->drupalLogin($regular);
    $this->drupalGet('admin/config/openintranet/notifications');
    $this->assertSession()->statusCodeEquals(403);

    $admin = $this->drupalCreateUser([
      'administer notification channels',
    ]);
    $this->drupalLogout();
    $this->drupalLogin($admin);
    $this->drupalGet('admin/config/openintranet/notifications');

    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('Enabled channels');
    $assert->pageTextContains('Enabled notification types');
    $assert->pageTextContains('Default audit retention (days)');
    $assert->pageTextContains('Channel kill switch');

    $this->submitForm([
      'enabled_channels[inbox]' => TRUE,
      'enabled_channels[email_core]' => FALSE,
      'enabled_channels[log_only]' => TRUE,
      'enabled_types[default]' => TRUE,
      'enabled_types[new_comment]' => FALSE,
      'retention_default_days' => 30,
      'kill_switch[inbox]' => FALSE,
      'kill_switch[email_core]' => FALSE,
      'kill_switch[log_only]' => TRUE,
    ], 'Save configuration');

    $assert->statusMessageContains('The configuration options have been saved.', 'status');

    $this->container->get('config.factory')
      ->reset('openintranet_notifications.settings');
    $config = $this->config('openintranet_notifications.settings');
    self::assertSame(['inbox', 'log_only'], $config->get('enabled_channels'));
    self::assertSame(['default'], $config->get('enabled_types'));
    self::assertSame(30, $config->get('retention.default_days'));
    self::assertTrue($config->get('kill_switch.log_only'));
    self::assertFalse($config->get('kill_switch.inbox'));
    self::assertSame('openintranet_notification_delivery', $config->get('queue.id'));
  }

  /**
   * Creates a notification for a browser-test recipient.
   */
  private function createNotification(
    UserInterface $recipient,
    string $subject,
    string $body = '',
    ?string $url = NULL,
    ?UserInterface $actor = NULL,
  ): NotificationInterface {
    $notification = Notification::create([
      'type' => 'default',
      'uid' => $recipient->id(),
      'actor_uid' => $actor?->id(),
      'subject' => $subject,
      'body' => $body,
      'url' => $url,
      'priority' => 'normal',
      'status' => 'delivered',
    ]);
    $notification->save();
    return $notification;
  }

  /**
   * Reloads a notification after a browser request.
   */
  private function reloadNotification(NotificationInterface $notification): NotificationInterface {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification');
    $storage->resetCache([$notification->id()]);
    $reloaded = $storage->load($notification->id());
    self::assertInstanceOf(NotificationInterface::class, $reloaded);
    return $reloaded;
  }

}
