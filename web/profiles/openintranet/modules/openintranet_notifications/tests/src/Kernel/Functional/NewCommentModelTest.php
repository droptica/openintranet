<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Functional;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\user\Entity\User;
use Symfony\Component\Yaml\Yaml;

/**
 * End-to-end test: a comment insert fires the new_comment ECA model.
 *
 * Proves the whole pipeline from a real ECA event through the
 * create_and_enqueue action, the dispatcher, the entity_author resolver
 * (targeting the commented node's author) and the factory render down to a
 * queued delivery — without any real mail leaving the test.
 *
 * @group openintranet_notifications
 */
final class NewCommentModelTest extends KernelTestBase {

  use CommentTestTrait;

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
    'node',
    'comment',
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
    $this->installEntitySchema('comment');
    $this->installEntitySchema('user_notification_settings');
    $this->installEntitySchema('openintranet_notification');
    $this->installEntitySchema('openintranet_notif_delivery');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filter', 'comment', 'openintranet_notifications']);

    // Anonymous + admin so the current user always resolves to a real account.
    User::create(['uid' => 0, 'name' => 'anonymous', 'status' => 0])->save();
    User::create(['uid' => 1, 'name' => 'admin', 'status' => 1])->save();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    // Create the comment bundle + field the model triggers on, matching the
    // recipe's comment_node_article bundle id.
    $this->addDefaultCommentField('node', 'article', 'comment_node_article', 1, 'comment_node_article');

    // Restrict delivery to test channels: the type's email channel has no
    // plugin and is not globally enabled, so no mail ever leaves the test.
    $this->config('openintranet_notifications.settings')
      ->set('enabled_channels', ['inbox', 'log_only'])
      ->save();

    // Replace the shipped new_comment type with a fixture whose channels
    // resolve to the test channels (inbox + log_only), forced so preferences
    // don't drop them; entity_author still targets the commented node's owner.
    $typeStorage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification_type');
    $typeStorage->delete($typeStorage->loadMultiple());
    NotificationType::create([
      'id' => 'new_comment',
      'label' => 'New comment',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox', 'log_only'],
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 0,
      'recipient_resolvers' => [
        ['id' => 'entity_author', 'configuration' => ['entity_key' => 'commented_entity']],
      ],
      'subject_template' => 'New comment on "[node:title]"',
      'body_template' => 'A new comment was posted.',
      'summary_template' => 'New comment',
    ])->save();
  }

  /**
   * A comment insert notifies the commented node's author via the ECA model.
   */
  public function testCommentInsertNotifiesArticleAuthor(): void {
    // The shipped ECA model installs from config/optional.
    $this->installNewCommentModel();

    // Author of the article (the expected recipient) and a different commenter.
    User::create(['uid' => 41, 'name' => 'author', 'status' => 1])->save();
    User::create(['uid' => 42, 'name' => 'commenter', 'status' => 1])->save();

    $article = Node::create([
      'type' => 'article',
      'title' => 'Hello world',
      'uid' => 41,
      'status' => 1,
    ]);
    $article->save();

    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => $article->id(),
      'field_name' => 'comment_node_article',
      'comment_type' => 'comment_node_article',
      'subject' => 'Nice post',
      'uid' => 42,
      'status' => 1,
    ]);

    // The model's eca_token_load_entity_ref step enforces view access on the
    // commented node; run the save as an authorized account so the chain runs
    // (mirrors eca_content's own ContentExecutionChainTest).
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $switcher */
    $switcher = $this->container->get('account_switcher');
    $switcher->switchTo(User::load(1));
    // Saving the comment fires content_entity:insert -> the new_comment model.
    $comment->save();
    $switcher->switchBack();

    $notifications = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification')
      ->loadMultiple();
    self::assertCount(1, $notifications, 'One notification was created for the article author.');

    $notification = reset($notifications);
    self::assertSame('new_comment', $notification->get('type')->value);
    self::assertSame(41, (int) $notification->get('uid')->target_id, 'The notification targets the article author, not the commenter.');

    $deliveries = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery')
      ->loadByProperties(['notification_id' => $notification->id()]);
    self::assertNotEmpty($deliveries, 'At least one delivery row was created.');

    // No real mail: the test collector must be empty (inbox/log channels only).
    $captured = $this->container->get('state')->get('system.test_mail_collector') ?? [];
    self::assertSame([], $captured, 'No mail was sent.');
  }

  /**
   * Installs the shipped new_comment ECA model from config/optional.
   *
   * The optional config is not auto-installed in a kernel test, so it is read
   * straight from the module's config/optional directory and saved.
   */
  private function installNewCommentModel(): void {
    $path = $this->container->get('extension.list.module')->getPath('openintranet_notifications')
      . '/config/optional/eca.eca.openintranet_notifications_new_comment.yml';
    $values = Yaml::parseFile($path);
    $this->container->get('entity_type.manager')
      ->getStorage('eca')
      ->create($values)
      ->trustData()
      ->save();
  }

}
