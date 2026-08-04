<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;

/**
 * Tests CRUD and getters of the notification_type config entity.
 *
 * @group openintranet_notifications
 */
final class NotificationTypeEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // The system and user modules provide the "action"/"user" entity types
    // ECA's action plugin manager and token data providers resolve while
    // rebuilding the container on config-entity save.
    'system',
    'user',
    'openintranet_notifications',
    'dynamic_entity_reference',
    'options',
    'token',
    'key',
    // ECA (eca:eca) depends on modeler_api:modeler_api (ECA 3.1.x); without it
    // the eca.processor service references a non-existent
    // template_token_resolver.
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
  ];

  /**
   * A saved notification type round-trips its values through the getters.
   */
  public function testCreateSaveReloadGetters(): void {
    $type = NotificationType::create([
      'id' => 'default',
      'label' => 'Default',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox'],
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 600,
    ]);
    $type->save();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification_type');
    $storage->resetCache();
    /** @var \Drupal\openintranet_notifications\Entity\NotificationTypeInterface $reloaded */
    $reloaded = $storage->load('default');

    self::assertSame('default', $reloaded->id());
    self::assertSame(['inbox', 'log_only'], $reloaded->getDefaultChannels());
    self::assertSame(['inbox'], $reloaded->getForcedChannels());
    self::assertSame('user_preferences', $reloaded->getDeliveryPolicy());
    self::assertSame(600, $reloaded->getDedupeWindow());
    // Defaults applied for keys not set on creation.
    self::assertSame('normal', $reloaded->getDefaultPriority());
    self::assertTrue($reloaded->status());
    self::assertTrue($reloaded->userCanOverride());
    self::assertSame([], $reloaded->getRecipientResolvers());
    self::assertSame([], $reloaded->getTemplateMap());

    $reloaded->disable()->save();
    $storage->resetCache();
    $disabled = $storage->load('default');
    self::assertInstanceOf(NotificationTypeInterface::class, $disabled);
    self::assertFalse($disabled->status());
  }

}
