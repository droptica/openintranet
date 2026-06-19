<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Functional;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\user\Entity\User;
use Symfony\Component\Yaml\Yaml;

/**
 * End-to-end test: the migrated process_j7zbyne model fires create_and_enqueue.
 *
 * Proves the article-notify model now routes through the new_article type and
 * its view_result_users resolver (no per-user custom-event fan-out, no
 * action_send_email_action): saving an article with the notify flag set queues
 * a new_article notification for every user the view returns, resets the flag
 * to 0, and sends no mail.
 *
 * @group openintranet_notifications
 */
final class ArticleNotifyMigrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'options',
    'dynamic_entity_reference',
    'token',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
    'eca_views',
    'node',
    'views',
    'openintranet_notifications',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user_notification_settings');
    $this->installEntitySchema('openintranet_notification');
    $this->installEntitySchema('openintranet_notif_delivery');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    // system.date_format.* so the old mail path's [node:changed] token renders.
    $this->installConfig(['system', 'filter', 'openintranet_notifications']);

    // Anonymous + admin so the current user always resolves to a real account.
    User::create(['uid' => 0, 'name' => 'anonymous', 'status' => 0])->save();
    User::create(['uid' => 1, 'name' => 'admin', 'status' => 1])->save();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    // The boolean field the model triggers on, mirroring the recipe. Created
    // via the field entity API so its storage table is installed.
    FieldStorageConfig::create([
      'field_name' => 'field_notify_all_users_about_new',
      'entity_type' => 'node',
      'type' => 'boolean',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_notify_all_users_about_new',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Notify all users about new content',
    ])->save();
    // The people view the resolver runs is config-only (no schema table).
    $this->installRecipeConfig([
      'views.view.user_admin_people',
    ], 'recipes/openintranet/config');

    // Restrict delivery to test channels: no mail ever leaves the test.
    $this->config('openintranet_notifications.settings')
      ->set('enabled_channels', ['inbox', 'log_only'])
      ->save();

    // The shipped new_article type defaults to email_core; rebuild it with the
    // test channels (inbox + log_only), forced so preferences don't drop them.
    // The view_result_users resolver still targets the people-view audience.
    NotificationType::create([
      'id' => 'new_article',
      'label' => 'New article',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox', 'log_only'],
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 0,
      'recipient_resolvers' => [
        [
          'id' => 'view_result_users',
          'configuration' => [
            'view_id' => 'user_admin_people',
            'display_id' => 'attachment_1',
          ],
        ],
      ],
      'subject_template' => 'New article: [node:title]',
      'body_template' => "A new article \"[node:title]\" has been published.\n\nRead more: [node:url]",
      'summary_template' => 'New article: [node:title]',
    ])->save();
  }

  /**
   * Saving a flagged article notifies the view audience and resets the flag.
   */
  public function testArticleInsertNotifiesViewAudience(): void {
    $this->installArticleNotifyModel();

    // Three active users the people view returns (uid != 0, status = 1).
    User::create(['uid' => 41, 'name' => 'u41', 'status' => 1])->save();
    User::create(['uid' => 42, 'name' => 'u42', 'status' => 1])->save();
    User::create(['uid' => 43, 'name' => 'u43', 'status' => 1])->save();

    $article = Node::create([
      'type' => 'article',
      'title' => 'Hello world',
      'uid' => 1,
      'status' => 1,
      'field_notify_all_users_about_new' => 1,
    ]);

    // The model's view query enforces access; run the save as an authorized
    // account so the chain runs (mirrors NewCommentModelTest).
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $switcher */
    $switcher = $this->container->get('account_switcher');
    $switcher->switchTo(User::load(1));
    // Saving the article fires content_entity:insert -> the migrated model.
    $article->save();
    $switcher->switchBack();

    $notifications = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification')
      ->loadMultiple();

    // One notification per active user the view returns (admin uid 1 + 41-43).
    $uids = array_map(
      static fn ($n) => (int) $n->get('uid')->target_id,
      $notifications,
    );
    sort($uids);
    self::assertSame([1, 41, 42, 43], $uids, 'A new_article notification was created for every active user the view returned.');
    foreach ($notifications as $notification) {
      self::assertSame('new_article', $notification->get('type')->value);
      // The article node must reach the renderer token data: the subject/body
      // render with the real article title (not an empty token clear). This is
      // the regression guard for the empty-render blocker (FIX 1/2).
      self::assertSame('New article: Hello world', (string) $notification->get('subject')->value, 'The subject rendered the article title.');
      self::assertStringContainsString('Hello world', (string) $notification->get('body')->value, 'The body rendered the article title.');
      self::assertStringNotContainsString('[node:', (string) $notification->get('body')->value, 'No unresolved node tokens remain in the body.');
      // The triggering article must land as the notification's source entity
      // (FIX 1: the event entity reaches the action); empty is the blocker.
      self::assertSame('node', (string) $notification->get('source_entity')->target_type, 'The source entity type is the article node.');
      self::assertSame((string) $article->id(), (string) $notification->get('source_entity')->target_id, 'The source entity references the triggering article.');
    }

    // The reset action set the flag back to 0.
    $saved = Node::load($article->id());
    self::assertSame('0', (string) $saved->get('field_notify_all_users_about_new')->value, 'The notify flag was reset to 0.');

    // No real mail: the test collector must be empty (inbox/log channels only).
    $captured = $this->container->get('state')->get('system.test_mail_collector') ?? [];
    self::assertSame([], $captured, 'No mail was sent.');
  }

  /**
   * Imports raw config YAMLs from a recipe directory into active storage.
   *
   * @param string[] $names
   *   The config object names (without the .yml extension).
   * @param string $relativeDir
   *   The recipe config directory relative to the project root.
   */
  private function installRecipeConfig(array $names, string $relativeDir): void {
    $root = $this->container->getParameter('app.root');
    $base = dirname($root) . '/' . $relativeDir;
    foreach ($names as $name) {
      $values = Yaml::parseFile($base . '/' . $name . '.yml');
      $this->config($name)->setData($values)->save(TRUE);
    }
  }

  /**
   * Installs the migrated process_j7zbyne ECA model from the recipe config.
   */
  private function installArticleNotifyModel(): void {
    $root = $this->container->getParameter('app.root');
    $path = dirname($root) . '/recipes/openintranet/config/eca.eca.process_j7zbyne.yml';
    $values = Yaml::parseFile($path);
    $this->container->get('entity_type.manager')
      ->getStorage('eca')
      ->create($values)
      ->trustData()
      ->save();
  }

}
