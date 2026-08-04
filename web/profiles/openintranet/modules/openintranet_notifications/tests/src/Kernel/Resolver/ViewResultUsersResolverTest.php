<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Resolver;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Resolver\RecipientResolverManager;
use Drupal\user\Entity\User;

/**
 * Tests the view_result_users recipient resolver.
 *
 * @group openintranet_notifications
 */
final class ViewResultUsersResolverTest extends KernelTestBase {

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
    'views',
    'openintranet_notifications',
    'openintranet_notifications_test',
  ];

  /**
   * The resolver plugin manager.
   */
  private RecipientResolverManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['openintranet_notifications_test']);
    $this->manager = $this->container->get('plugin.manager.notification_recipient_resolver');

    // 41, 43 active; 42 blocked.
    User::create(['uid' => 41, 'name' => 'u41', 'status' => 1])->save();
    User::create(['uid' => 42, 'name' => 'u42', 'status' => 0])->save();
    User::create(['uid' => 43, 'name' => 'u43', 'status' => 1])->save();
  }

  /**
   * Extracts the sorted uid set from a list of recipients.
   *
   * @param \Drupal\openintranet_notifications\Dto\NotificationRecipient[] $recipients
   *   The recipients.
   *
   * @return int[]
   *   The sorted uids.
   */
  private function uids(array $recipients): array {
    $uids = array_map(static fn (NotificationRecipient $r): int => (int) $r->id, $recipients);
    sort($uids);
    return $uids;
  }

  /**
   * Each result row's uid becomes a user recipient; blocked are excluded.
   */
  public function testResolvesViewResultUids(): void {
    $resolver = $this->manager->createInstance('view_result_users', [
      'view_id' => 'test_notification_recipients',
      'display_id' => 'default',
    ]);
    $recipients = $resolver->resolve([]);

    // The view filters status = 1, so 42 (blocked) is absent.
    self::assertSame([41, 43], $this->uids($recipients));
    foreach ($recipients as $recipient) {
      self::assertTrue($recipient->isUser());
      self::assertNotNull($recipient->account);
    }
  }

  /**
   * A non-existent view returns an empty set without a fatal.
   */
  public function testMissingViewYieldsNoRecipients(): void {
    $resolver = $this->manager->createInstance('view_result_users', [
      'view_id' => 'does_not_exist',
      'display_id' => 'default',
    ]);
    self::assertSame([], $resolver->resolve([]));
  }

  /**
   * Missing view_id config returns an empty set.
   */
  public function testMissingConfigYieldsNoRecipients(): void {
    $resolver = $this->manager->createInstance('view_result_users', []);
    self::assertSame([], $resolver->resolve([]));
  }

}
