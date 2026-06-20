<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Channel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Channel\NotificationChannelInterface;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\user\Entity\User;

/**
 * Tests the email_core channel send path and core-mail capture.
 *
 * KernelTestBase forces the test_mail_collector mail backend, so a successful
 * send is captured in the system.test_mail_collector state and asserted here.
 *
 * @group openintranet_notifications
 */
final class EmailCoreChannelTest extends KernelTestBase {

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
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
  }

  /**
   * Instantiates the channel under test.
   */
  private function channel(): NotificationChannelInterface {
    /** @var \Drupal\openintranet_notifications\Channel\ChannelPluginManager $manager */
    $manager = $this->container->get('plugin.manager.notification_channel');
    $channel = $manager->createInstance('email_core');
    self::assertInstanceOf(NotificationChannelInterface::class, $channel);
    return $channel;
  }

  /**
   * Sending to a user with a mail succeeds and captures a mail.
   */
  public function testSendToUserCapturesMail(): void {
    $user = User::create([
      'name' => 'recipient',
      'mail' => 'recipient@example.com',
      'status' => 1,
    ]);
    $user->save();

    $recipient = new NotificationRecipient(
      type: 'user',
      id: (int) $user->id(),
      langcode: 'en',
      account: $user,
    );
    $message = new NotificationMessage(subject: 'Hello there', body: 'Message body.');

    $result = $this->channel()->send($recipient, $message);

    self::assertTrue($result->success);

    $captured = \Drupal::state()->get('system.test_mail_collector');
    self::assertIsArray($captured);
    self::assertCount(1, $captured);
    $mail = end($captured);
    self::assertSame('recipient@example.com', $mail['to']);
    self::assertSame('Hello there', $mail['subject']);
  }

  /**
   * Sending to a recipient with no address is a permanent NO_ADDRESS failure.
   */
  public function testSendWithoutAddressIsPermanentFailure(): void {
    $recipient = new NotificationRecipient(type: 'email', value: NULL, langcode: 'en');
    $message = new NotificationMessage(subject: 'Subject', body: 'Body');

    $result = $this->channel()->send($recipient, $message);

    self::assertFalse($result->success);
    self::assertFalse($result->retryable);
    self::assertSame('NO_ADDRESS', $result->errorCode);
  }

}
