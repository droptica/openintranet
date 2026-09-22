<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications_push_framework\Kernel;

use Drupal\Tests\openintranet_notifications\Kernel\Channel\ChannelContractTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\oin_pf_test_channel\Plugin\PushFrameworkChannel\TestChannel;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\push_framework\ChannelPluginInterface;
use Drupal\user\Entity\User;

/**
 * Channel contract conformance for the push channel.
 *
 * The channel addresses only Drupal users (push targets a user account); a
 * non-user recipient is unaddressable. Its failing scenario drives the
 * "no applicable push channel" branch by deactivating the test pf channel.
 *
 * @group openintranet_notifications
 */
final class PushChannelContractTest extends ChannelContractTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
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
    'node',
    'advancedqueue',
    'push_framework',
    'openintranet_notifications_push_framework',
    'oin_pf_test_channel',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    TestChannel::$active = TRUE;
    TestChannel::$applicable = TRUE;
    TestChannel::$sendStatus = ChannelPluginInterface::RESULT_STATUS_SUCCESS;
    TestChannel::$throwOnSend = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function channelId(): string {
    return 'push';
  }

  /**
   * {@inheritdoc}
   */
  protected function addressableRecipient(): NotificationRecipient {
    $user = User::create([
      'name' => 'addressable',
      'mail' => 'addressable@example.com',
      'status' => 1,
    ]);
    $user->save();
    return new NotificationRecipient(
      type: 'user',
      id: (int) $user->id(),
      langcode: 'en',
      account: $user,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function unaddressableRecipient(): ?NotificationRecipient {
    return new NotificationRecipient(type: 'email', value: 'someone@example.com', langcode: 'en');
  }

  /**
   * {@inheritdoc}
   */
  protected function failingScenario(): ?array {
    TestChannel::$active = FALSE;
    $user = User::create([
      'name' => 'failing',
      'mail' => 'failing@example.com',
      'status' => 1,
    ]);
    $user->save();
    $recipient = new NotificationRecipient(
      type: 'user',
      id: (int) $user->id(),
      langcode: 'en',
      account: $user,
    );
    $node = Node::create(['type' => 'page', 'title' => 'About']);
    $node->save();
    $message = new NotificationMessage(
      subject: 'Subject',
      body: 'Body',
      payload: ['entity' => $node],
    );
    return [$recipient, $message];
  }

}
