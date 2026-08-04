<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Type;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;

/**
 * Tests the shipped notification_type config entities.
 *
 * The "default" and "new_comment" types ship in the module's config/install
 * and are installed when the module is enabled. The "new_article" and
 * "consultation_review_assigned" types ship in recipe config directories; they
 * are not auto-installed, so the test imports the YAML files directly and lets
 * the strict config-schema checker validate them.
 *
 * @group openintranet_notifications
 */
final class NotificationTypeInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // The system and user modules provide the "action"/"user" entity types
    // ECA's action plugin manager and token data providers resolve while
    // rebuilding the container during module install.
    'system',
    'user',
    'field',
    'text',
    'options',
    'dynamic_entity_reference',
    'token',
    // ECA (eca:eca) depends on modeler_api:modeler_api (ECA 3.1.x); without it
    // the eca.processor service references a non-existent
    // template_token_resolver.
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
    'openintranet_notifications',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['openintranet_notifications']);
  }

  /**
   * The "default" type ships in config/install with the documented profile.
   */
  public function testDefaultTypeInstalled(): void {
    $type = $this->loadType('default');

    self::assertSame('Default', $type->label());
    self::assertSame('general', $type->getCategory());
    self::assertSame('normal', $type->getDefaultPriority());
    self::assertSame(['inbox', 'email_core'], $type->getDefaultChannels());
    self::assertSame(['inbox'], $type->getForcedChannels());
    self::assertSame([], $type->getRecipientResolvers());
    self::assertSame('user_preferences', $type->getDeliveryPolicy());
    self::assertSame('token_text', $type->getTemplateRenderer());
    self::assertSame(600, $type->getDedupeWindow());
    self::assertSame(0, $type->getRateLimit());
    self::assertSame(3600, $type->getRateLimitWindow());
    self::assertTrue($type->userCanOverride());
    self::assertSame(90, $type->getAuditRetentionDays());
    self::assertTrue($type->status());
  }

  /**
   * The "new_comment" type ships in config/install with the social profile.
   */
  public function testNewCommentTypeInstalled(): void {
    $type = $this->loadType('new_comment');

    self::assertSame('social', $type->getCategory());
    self::assertSame(['inbox', 'email_core'], $type->getDefaultChannels());
    self::assertSame([], $type->getForcedChannels());
    self::assertSame('user_preferences', $type->getDeliveryPolicy());
    self::assertSame('token_text', $type->getTemplateRenderer());
    self::assertSame(600, $type->getDedupeWindow());
    self::assertSame(0, $type->getRateLimit());
    self::assertSame(3600, $type->getRateLimitWindow());
    self::assertTrue($type->status());
    self::assertSame(
      [
        [
          'id' => 'entity_author',
          'configuration' => ['entity_key' => 'commented_entity'],
        ],
      ],
      $type->getRecipientResolvers(),
    );
  }

  /**
   * The "new_article" type ships in the openintranet recipe config dir.
   */
  public function testNewArticleTypeFromRecipe(): void {
    $type = $this->importRecipeType(
      'recipes/openintranet/config',
      'new_article',
    );

    self::assertSame('content', $type->getCategory());
    self::assertSame(['inbox', 'email_core'], $type->getDefaultChannels());
    self::assertSame([], $type->getForcedChannels());
    self::assertSame('user_preferences', $type->getDeliveryPolicy());
    self::assertSame('token_text', $type->getTemplateRenderer());
    self::assertSame(600, $type->getDedupeWindow());
    self::assertSame(0, $type->getRateLimit());
    self::assertSame(3600, $type->getRateLimitWindow());
    self::assertTrue($type->status());
    self::assertSame(
      [
        [
          'id' => 'view_result_users',
          'configuration' => [
            'view_id' => 'user_admin_people',
            'display_id' => 'attachment_1',
          ],
        ],
      ],
      $type->getRecipientResolvers(),
    );
    self::assertSame('New article: [node:title]', $type->getSubjectTemplate());
  }

  /**
   * The "consultation_review_assigned" type ships in the consultation recipe.
   */
  public function testConsultationReviewAssignedTypeFromRecipe(): void {
    $type = $this->importRecipeType(
      'recipes/consultation_process/config',
      'consultation_review_assigned',
    );

    self::assertSame('consultation', $type->getCategory());
    self::assertSame(['inbox', 'email_core'], $type->getDefaultChannels());
    self::assertSame([], $type->getForcedChannels());
    self::assertSame('user_preferences', $type->getDeliveryPolicy());
    self::assertSame('token_text', $type->getTemplateRenderer());
    self::assertSame(600, $type->getDedupeWindow());
    self::assertSame(0, $type->getRateLimit());
    self::assertSame(3600, $type->getRateLimitWindow());
    self::assertTrue($type->status());
    self::assertSame(
      [
        ['id' => 'entity_author', 'configuration' => []],
      ],
      $type->getRecipientResolvers(),
    );
  }

  /**
   * Loads a notification type by id from active config storage.
   */
  private function loadType(string $id): NotificationTypeInterface {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification_type');
    $storage->resetCache();
    $type = $storage->load($id);
    self::assertInstanceOf(NotificationTypeInterface::class, $type);
    return $type;
  }

  /**
   * Imports a recipe-shipped type YAML and returns the saved entity.
   *
   * The strict config-schema checker (enabled by default in KernelTestBase)
   * validates the imported config against the entity schema on save.
   *
   * @param string $relative_dir
   *   The repository-relative directory holding the recipe config (relative to
   *   the project root, which is the parent of the Drupal docroot).
   * @param string $id
   *   The notification type id.
   *
   * @return \Drupal\openintranet_notifications\Entity\NotificationTypeInterface
   *   The saved notification type entity.
   */
  private function importRecipeType(string $relative_dir, string $id): NotificationTypeInterface {
    $config_name = 'openintranet_notifications.openintranet_notification_type.' . $id;
    // Recipes live at the project root, one level above the Drupal docroot
    // (app.root points at the docroot).
    $project_root = dirname((string) $this->container->getParameter('app.root'));
    $file = $project_root . '/' . $relative_dir . '/' . $config_name . '.yml';
    self::assertFileExists($file);

    $data = Yaml::decode((string) file_get_contents($file));
    // Strip the recipe metadata that active config storage does not keep.
    unset($data['uuid'], $data['_core']);

    $this->config($config_name)->setData($data)->save();

    return $this->loadType($id);
  }

}
