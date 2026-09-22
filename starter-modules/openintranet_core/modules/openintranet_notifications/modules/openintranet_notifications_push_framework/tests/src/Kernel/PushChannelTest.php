<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications_push_framework\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\oin_pf_test_channel\Plugin\PushFrameworkChannel\TestChannel;
use Drupal\openintranet_notifications\Channel\NotificationChannelInterface;
use Drupal\openintranet_notifications\Dto\DeliveryResult;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\push_framework\ChannelPluginInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Behaviour of the push channel adapter delegating to Push Framework.
 *
 * @group openintranet_notifications
 */
final class PushChannelTest extends KernelTestBase {

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
   * The plugin manager discovers the push channel from the submodule.
   */
  public function testChannelIsDiscovered(): void {
    $manager = $this->container->get('plugin.manager.openintranet_notification_channel');
    self::assertArrayHasKey('push', $manager->getDefinitions());
  }

  /**
   * The isAvailable() call reflects the presence of an active pf channel.
   */
  public function testIsAvailableTracksActivePfChannel(): void {
    self::assertTrue($this->channel()->isAvailable());

    TestChannel::$active = FALSE;
    self::assertFalse($this->channel()->isAvailable());
  }

  /**
   * The canSendTo() call requires a user with an applicable active channel.
   */
  public function testCanSendToRequiresUserAndApplicableChannel(): void {
    $channel = $this->channel();
    self::assertTrue($channel->canSendTo($this->userRecipient()));

    // Non-user recipients are never addressable.
    self::assertFalse($channel->canSendTo(
      new NotificationRecipient(type: 'email', value: 'x@example.com'),
    ));

    // No applicable pf channel -> cannot send.
    TestChannel::$applicable = FALSE;
    self::assertFalse($this->channel()->canSendTo($this->userRecipient()));
  }

  /**
   * A SUCCESS status from a pf channel maps to a successful DeliveryResult.
   */
  public function testSendSuccess(): void {
    TestChannel::$sendStatus = ChannelPluginInterface::RESULT_STATUS_SUCCESS;
    $result = $this->channel()->send($this->userRecipient(), $this->message());
    self::assertTrue($result->success);
  }

  /**
   * A RETRY status maps to a retryable failure with code PUSH_RETRY.
   */
  public function testSendRetry(): void {
    TestChannel::$sendStatus = ChannelPluginInterface::RESULT_STATUS_RETRY;
    $result = $this->channel()->send($this->userRecipient(), $this->message());
    self::assertFalse($result->success);
    self::assertTrue($result->retryable);
    self::assertSame('PUSH_RETRY', $result->errorCode);
  }

  /**
   * A FAILED status maps to a permanent failure with code PUSH_FAILED.
   */
  public function testSendFailed(): void {
    TestChannel::$sendStatus = ChannelPluginInterface::RESULT_STATUS_FAILED;
    $result = $this->channel()->send($this->userRecipient(), $this->message());
    self::assertFalse($result->success);
    self::assertFalse($result->retryable);
    self::assertSame('PUSH_FAILED', $result->errorCode);
  }

  /**
   * No applicable active channel maps to permanent failure NO_PUSH_CHANNEL.
   */
  public function testSendNoApplicableChannel(): void {
    TestChannel::$applicable = FALSE;
    $result = $this->channel()->send($this->userRecipient(), $this->message());
    self::assertFalse($result->success);
    self::assertFalse($result->retryable);
    self::assertSame('NO_PUSH_CHANNEL', $result->errorCode);
  }

  /**
   * A non-user recipient maps to permanent failure NO_USER.
   */
  public function testSendNonUserRecipient(): void {
    $result = $this->channel()->send(
      new NotificationRecipient(type: 'email', value: 'x@example.com'),
      $this->message(),
    );
    self::assertFalse($result->success);
    self::assertFalse($result->retryable);
    self::assertSame('NO_USER', $result->errorCode);
  }

  /**
   * No notification entity in the payload maps to PUSH_NO_ENTITY.
   */
  public function testSendNoEntity(): void {
    $message = new NotificationMessage(subject: 'S', body: 'B');
    $result = $this->channel()->send($this->userRecipient(), $message);
    self::assertFalse($result->success);
    self::assertFalse($result->retryable);
    self::assertSame('PUSH_NO_ENTITY', $result->errorCode);
  }

  /**
   * The send() call never propagates a transport exception (it classifies it).
   */
  public function testSendNeverThrows(): void {
    TestChannel::$throwOnSend = TRUE;
    $result = $this->channel()->send($this->userRecipient(), $this->message());
    self::assertInstanceOf(DeliveryResult::class, $result);
    self::assertFalse($result->success);
    self::assertTrue($result->retryable);
    self::assertSame('PUSH_EXCEPTION', $result->errorCode);
  }

  /**
   * Instantiates the push channel under test.
   */
  private function channel(): NotificationChannelInterface {
    $manager = $this->container->get('plugin.manager.openintranet_notification_channel');
    $channel = $manager->createInstance('push');
    self::assertInstanceOf(NotificationChannelInterface::class, $channel);
    return $channel;
  }

  /**
   * Builds a saved user recipient.
   */
  private function userRecipient(): NotificationRecipient {
    $user = User::create([
      'name' => 'recipient' . substr(md5((string) microtime(TRUE)), 0, 6),
      'mail' => 'recipient@example.com',
      'status' => 1,
    ]);
    $user->save();
    self::assertInstanceOf(UserInterface::class, $user);
    return new NotificationRecipient(
      type: 'user',
      id: (int) $user->id(),
      langcode: 'en',
      account: $user,
    );
  }

  /**
   * Builds a message carrying a source node entity in its payload.
   */
  private function message(): NotificationMessage {
    $node = Node::create(['type' => 'page', 'title' => 'About']);
    $node->save();
    return new NotificationMessage(
      subject: 'Subject',
      body: 'Body',
      payload: ['entity' => $node],
    );
  }

}
