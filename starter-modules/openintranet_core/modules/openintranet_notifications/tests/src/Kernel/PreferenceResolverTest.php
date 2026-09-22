<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\UserNotificationSettings;
use Drupal\openintranet_notifications\Service\PreferenceResolverInterface;

/**
 * Tests the preference_resolver service and user_notification_settings entity.
 *
 * @group openintranet_notifications
 */
final class PreferenceResolverTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'openintranet_notifications',
    'dynamic_entity_reference',
    'options',
    'text',
    'token',
    'key',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
  ];

  /**
   * The preference resolver under test.
   */
  private PreferenceResolverInterface $resolver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('user_notification_settings');
    $this->installConfig(['openintranet_notifications']);
    $this->resolver = $this->container->get('openintranet_notifications.preference_resolver');
  }

  /**
   * The service is wired against its interface.
   */
  public function testServiceImplementsInterface(): void {
    self::assertInstanceOf(PreferenceResolverInterface::class, $this->resolver);
  }

  /**
   * The stored channel preference is returned verbatim.
   */
  public function testIsEnabledReadsStoredPreference(): void {
    UserNotificationSettings::create([
      'uid' => 7,
      'preferences' => [
        'default' => ['inbox' => TRUE, 'log_only' => TRUE],
      ],
    ])->save();

    self::assertTrue($this->resolver->isEnabled(7, 'default', 'inbox'));
    self::assertTrue($this->resolver->isEnabled(7, 'default', 'log_only'));
  }

  /**
   * With no explicit pref the resolver falls back to the global defaults.
   */
  public function testFallsBackToGlobalDefaults(): void {
    // No settings entity for uid 9; settings ship default.inbox=TRUE,
    // default.log_only=FALSE.
    self::assertTrue($this->resolver->isEnabled(9, 'default', 'inbox'));
    self::assertFalse($this->resolver->isEnabled(9, 'default', 'log_only'));
    // Unknown type/channel with no default at all is off.
    self::assertFalse($this->resolver->isEnabled(9, 'unknown', 'inbox'));
  }

  /**
   * The settings entity is auto-created and persisted exactly once.
   */
  public function testLoadOrCreateForAutoCreates(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('user_notification_settings');
    self::assertCount(0, $storage->loadByProperties(['uid' => 11]));

    $settings = $this->resolver->loadOrCreateFor(11);
    self::assertInstanceOf(UserNotificationSettings::class, $settings);
    self::assertFalse($settings->isNew());
    self::assertSame(11, (int) $settings->get('uid')->target_id);

    // A second call returns the same persisted entity, not a duplicate.
    $again = $this->resolver->loadOrCreateFor(11);
    self::assertSame((int) $settings->id(), (int) $again->id());
    self::assertCount(1, $storage->loadByProperties(['uid' => 11]));
  }

  /**
   * The stored quiet-hours window is returned, or NULL when none is set.
   */
  public function testGetQuietHours(): void {
    self::assertNull($this->resolver->getQuietHours(13));

    UserNotificationSettings::create([
      'uid' => 13,
      'quiet_hours_start' => '22:00',
      'quiet_hours_end' => '07:00',
      'quiet_hours_tz' => 'Europe/Warsaw',
    ])->save();

    self::assertSame(
      ['start' => '22:00', 'end' => '07:00', 'tz' => 'Europe/Warsaw'],
      $this->resolver->getQuietHours(13),
    );
  }

}
