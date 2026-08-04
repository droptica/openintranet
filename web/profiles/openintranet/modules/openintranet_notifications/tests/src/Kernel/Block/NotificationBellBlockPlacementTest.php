<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Block;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;

/**
 * Tests the shipped notification bell block placement (§14, Chunk 5B; Fix #1).
 *
 * The placement ships in the openintranet_notifications recipe config dir under
 * the legacy machine name openintranet_theme_usernotifications so the theme
 * template suggestion still matches and the bell renders via {{ content }}. It
 * is not auto-installed (the recipe is applied separately), so the test loads
 * the YAML directly and validates it against the block.block config schema.
 *
 * @group openintranet_notifications
 */
final class NotificationBellBlockPlacementTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'block',
    'field',
    'text',
    'options',
    'dynamic_entity_reference',
    'token',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
    'openintranet_notifications',
  ];

  /**
   * The bell placement config is schema-valid and wires the bell plugin.
   */
  public function testBellPlacementIsSchemaValidAndPlacesBell(): void {
    $config_name = 'block.block.openintranet_theme_usernotifications';
    // Recipes live at the project root, one level above the Drupal docroot.
    $project_root = dirname((string) $this->container->getParameter('app.root'));
    $dir = $project_root . '/recipes/openintranet_notifications/config';
    $file = $dir . '/' . $config_name . '.yml';
    self::assertFileExists($file);

    $data = Yaml::decode((string) file_get_contents($file));

    // The placement replaces the legacy default_content static block_content at
    // the same region/position so the theme template renders the bell.
    self::assertSame('openintranet_notification_bell', $data['plugin']);
    self::assertSame('openintranet_notification_bell', $data['settings']['id']);
    self::assertSame('top_header_user_actions', $data['region']);
    self::assertSame('openintranet_theme', $data['theme']);
    self::assertSame('openintranet_theme_usernotifications', $data['id']);
    self::assertContains('openintranet_notifications', $data['dependencies']['module']);
    self::assertContains('openintranet_theme', $data['dependencies']['theme']);

    // Validate against the block.block.* config schema (the block module ships
    // the typed-config definition; the bell block has no custom settings schema
    // so it resolves the generic block_settings type).
    $this->assertConfigSchema(
      $this->container->get('config.typed'),
      $config_name,
      $data,
    );
  }

  /**
   * The recipe config directory is loadable as a FileStorage collection.
   */
  public function testRecipeConfigDirectoryIsReadable(): void {
    $project_root = dirname((string) $this->container->getParameter('app.root'));
    $dir = $project_root . '/recipes/openintranet_notifications/config';
    $storage = new FileStorage($dir);

    self::assertTrue(
      $storage->exists('block.block.openintranet_theme_usernotifications'),
      'The bell placement is present in the recipe config storage.',
    );
  }

}
