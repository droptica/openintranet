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
 * End-to-end test: the migrated process_r9ldkzi model fires create_and_enqueue.
 *
 * Proves the consultation-review model now routes through the
 * consultation_review_assigned type and its entity_author resolver (no
 * action_send_email_action): inserting a review references its parent
 * consultation (still loaded into the [consultation] token for the deadline
 * template) and queues a notification for the review's author, with no mail.
 *
 * @group openintranet_notifications
 */
final class ConsultationReviewMigrationTest extends KernelTestBase {

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
    'datetime',
    'dynamic_entity_reference',
    'token',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
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
    $this->installConfig(['system', 'filter', 'openintranet_notifications']);

    // Anonymous + admin so the current user always resolves to a real account.
    User::create(['uid' => 0, 'name' => 'anonymous', 'status' => 0])->save();
    User::create(['uid' => 1, 'name' => 'admin', 'status' => 1])->save();

    NodeType::create(['type' => 'consultation', 'name' => 'Consultation'])->save();
    NodeType::create(['type' => 'consultation_review', 'name' => 'Consultation Review'])->save();

    // The review -> consultation reference and the consultation deadline,
    // created via the field entity API so their storage tables are installed.
    FieldStorageConfig::create([
      'field_name' => 'field_consultation_review_ref',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_consultation_review_ref',
      'entity_type' => 'node',
      'bundle' => 'consultation_review',
      'label' => 'Consultation',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => ['target_bundles' => ['consultation' => 'consultation']],
      ],
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_consultation_deadline',
      'entity_type' => 'node',
      'type' => 'datetime',
      'settings' => ['datetime_type' => 'date'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_consultation_deadline',
      'entity_type' => 'node',
      'bundle' => 'consultation',
      'label' => 'Deadline',
    ])->save();

    // Restrict delivery to test channels: no mail ever leaves the test.
    $this->config('openintranet_notifications.settings')
      ->set('enabled_channels', ['inbox', 'log_only'])
      ->save();

    // The shipped consultation_review_assigned type defaults to email_core;
    // rebuild it with the test channels (inbox + log_only), forced so
    // preferences don't drop them. entity_author still targets the owner.
    NotificationType::create([
      'id' => 'consultation_review_assigned',
      'label' => 'Consultation review assigned',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox', 'log_only'],
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 0,
      'recipient_resolvers' => [
        ['id' => 'entity_author', 'configuration' => []],
      ],
      'subject_template' => 'You have been added as reviewer of "[node:title]"',
      'body_template' => 'Deadline: [consultation:field_consultation_deadline]',
      'summary_template' => 'Review assigned: [node:title]',
    ])->save();
  }

  /**
   * Inserting a review notifies its author via the migrated model.
   */
  public function testReviewInsertNotifiesAuthor(): void {
    $this->installConsultationReviewModel();

    // The reviewer (review author) is the expected recipient.
    User::create(['uid' => 42, 'name' => 'reviewer', 'status' => 1])->save();

    $consultation = Node::create([
      'type' => 'consultation',
      'title' => 'Budget consultation',
      'uid' => 1,
      'status' => 1,
      'field_consultation_deadline' => '2026-12-31',
    ]);
    $consultation->save();

    $review = Node::create([
      'type' => 'consultation_review',
      'title' => 'Please review the budget',
      'uid' => 42,
      'status' => 1,
      'field_consultation_review_ref' => $consultation->id(),
    ]);

    // Run the save as an authorized account so the model's load step (which
    // enforces access on the loaded consultation) runs.
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $switcher */
    $switcher = $this->container->get('account_switcher');
    $switcher->switchTo(User::load(1));
    // Saving the review fires content_entity:insert -> the migrated model.
    $review->save();
    $switcher->switchBack();

    $notifications = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification')
      ->loadMultiple();
    self::assertCount(1, $notifications, 'One consultation_review_assigned notification was created for the review author.');

    $notification = reset($notifications);
    self::assertSame('consultation_review_assigned', $notification->get('type')->value);
    self::assertSame(42, (int) $notification->get('uid')->target_id, 'The notification targets the review author (the reviewer).');

    // No real mail: the test collector must be empty (inbox/log channels only).
    $captured = $this->container->get('state')->get('system.test_mail_collector') ?? [];
    self::assertSame([], $captured, 'No mail was sent.');
  }

  /**
   * Installs the migrated process_r9ldkzi ECA model from the recipe config.
   */
  private function installConsultationReviewModel(): void {
    $root = $this->container->getParameter('app.root');
    $path = dirname($root) . '/recipes/consultation_process/config/eca.eca.process_r9ldkzi.yml';
    $values = Yaml::parseFile($path);
    $this->container->get('entity_type.manager')
      ->getStorage('eca')
      ->create($values)
      ->trustData()
      ->save();
  }

}
