<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications_easy_email\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\easy_email\Entity\EasyEmailType;
use Drupal\openintranet_notifications\Channel\NotificationChannelInterface;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\user\Entity\User;

/**
 * Tests the Easy Email channel send mapping, availability and addressing.
 *
 * The easy_email.handler is replaced with an in-memory double so create+send
 * is exercised without Easy Email's real mail/token/render pipeline; the live
 * send is covered by manual QA (see TestEmailHandler).
 *
 * @group openintranet_notifications
 */
final class EasyEmailChannelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'dynamic_entity_reference',
    'options',
    'text',
    'token',
    'key',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
    'filter',
    'file',
    'easy_email',
    'openintranet_notifications',
    'openintranet_notifications_easy_email',
  ];

  /**
   * The configured Easy Email template id used by the channel under test.
   */
  private const EMAIL_TYPE = 'oin_test';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('easy_email');
    $this->createEmailType(self::EMAIL_TYPE);
  }

  /**
   * Creates a minimal Easy Email template.
   */
  private function createEmailType(string $id): void {
    EasyEmailType::create([
      'id' => $id,
      'label' => 'OIN test',
      'key' => '',
      'recipient' => [],
      'subject' => 'Default subject',
      'bodyHtml' => ['value' => 'Default body', 'format' => 'plain_text'],
      'generateBodyPlain' => TRUE,
      'saveEmail' => FALSE,
    ])->save();
  }

  /**
   * Replaces easy_email.handler with a double running in the given mode.
   */
  private function useHandler(string $mode): void {
    $inner = $this->container->get('easy_email.handler');
    $this->container->set('easy_email.handler', new TestEmailHandler($inner, $mode));
  }

  /**
   * Instantiates the channel with the given plugin configuration.
   */
  private function channel(array $configuration = ['email_type' => self::EMAIL_TYPE]): NotificationChannelInterface {
    /** @var \Drupal\openintranet_notifications\Channel\ChannelPluginManager $manager */
    $manager = $this->container->get('plugin.manager.notification_channel');
    $channel = $manager->createInstance('email_easy_email', $configuration);
    self::assertInstanceOf(NotificationChannelInterface::class, $channel);
    return $channel;
  }

  /**
   * An email-type recipient the channel can address.
   */
  private function recipient(): NotificationRecipient {
    return new NotificationRecipient(type: 'email', value: 'rcpt@example.com', langcode: 'en');
  }

  /**
   * A simple message.
   */
  private function message(): NotificationMessage {
    return new NotificationMessage(subject: 'Subj', body: '<p>Body</p>', summary: 'Sum');
  }

  /**
   * Enabling the submodule makes the channel discoverable.
   */
  public function testChannelIsDiscovered(): void {
    $manager = $this->container->get('plugin.manager.notification_channel');
    self::assertArrayHasKey('email_easy_email', $manager->getDefinitions());
  }

  /**
   * The channel is unavailable when email_type is unset.
   */
  public function testIsUnavailableWhenTypeUnset(): void {
    self::assertFalse($this->channel(['email_type' => ''])->isAvailable());
  }

  /**
   * The channel is unavailable when the configured type does not exist.
   */
  public function testIsUnavailableWhenTypeMissing(): void {
    self::assertFalse($this->channel(['email_type' => 'does_not_exist'])->isAvailable());
  }

  /**
   * The channel is available when the configured type exists.
   */
  public function testIsAvailableWhenTypeExists(): void {
    self::assertTrue($this->channel()->isAvailable());
  }

  /**
   * The address resolves to a user mail, an email value, else NULL.
   */
  public function testGetRecipientAddress(): void {
    $channel = $this->channel();

    $account = User::create([
      'name' => 'rcpt',
      'mail' => 'user@example.com',
      'status' => 1,
    ]);
    $account->save();
    $userRecipient = new NotificationRecipient(type: 'user', id: (int) $account->id(), langcode: 'en', account: $account);
    self::assertSame('user@example.com', $channel->getRecipientAddress($userRecipient));

    self::assertSame('rcpt@example.com', $channel->getRecipientAddress($this->recipient()));

    $phone = new NotificationRecipient(type: 'phone', value: '+48123456789', langcode: 'en');
    self::assertNull($channel->getRecipientAddress($phone));
  }

  /**
   * A recipient with no resolvable address is a permanent NO_ADDRESS failure.
   */
  public function testNoAddressIsPermanentFailure(): void {
    $this->useHandler('sent');
    $phone = new NotificationRecipient(type: 'phone', value: '+48123456789', langcode: 'en');

    $result = $this->channel()->send($phone, $this->message());

    self::assertFalse($result->success);
    self::assertFalse($result->retryable);
    self::assertSame('NO_ADDRESS', $result->errorCode);
  }

  /**
   * An unset email_type is a permanent NO_EMAIL_TYPE failure.
   */
  public function testNoEmailTypeIsPermanentFailure(): void {
    $this->useHandler('sent');

    $result = $this->channel(['email_type' => ''])->send($this->recipient(), $this->message());

    self::assertFalse($result->success);
    self::assertFalse($result->retryable);
    self::assertSame('NO_EMAIL_TYPE', $result->errorCode);
  }

  /**
   * A configured but missing email_type is a permanent NO_EMAIL_TYPE failure.
   */
  public function testMissingEmailTypeIsPermanentFailure(): void {
    $this->useHandler('sent');

    $result = $this->channel(['email_type' => 'does_not_exist'])->send($this->recipient(), $this->message());

    self::assertFalse($result->success);
    self::assertFalse($result->retryable);
    self::assertSame('NO_EMAIL_TYPE', $result->errorCode);
  }

  /**
   * A sent email maps to success and is addressed/subjected from the message.
   */
  public function testSentEmailIsSuccess(): void {
    $this->useHandler('sent');
    $handler = $this->container->get('easy_email.handler');

    $result = $this->channel()->send($this->recipient(), $this->message());

    self::assertTrue($result->success);
    self::assertInstanceOf(TestEmailHandler::class, $handler);
    self::assertSame(self::EMAIL_TYPE, $handler->createValues['type']);
    self::assertNotNull($handler->createdEmail);
    self::assertSame(['rcpt@example.com'], $handler->createdEmail->getRecipientAddresses());
    self::assertSame('Subj', $handler->createdEmail->getSubject());
  }

  /**
   * A returned-but-unsent email maps to a retryable EASY_EMAIL_FAILED failure.
   */
  public function testUnsentEmailIsRetryableFailure(): void {
    $this->useHandler('unsent');

    $result = $this->channel()->send($this->recipient(), $this->message());

    self::assertFalse($result->success);
    self::assertTrue($result->retryable);
    self::assertSame('EASY_EMAIL_FAILED', $result->errorCode);
  }

  /**
   * A genuine non-duplicate FALSE maps to a retryable EASY_EMAIL_FAILED.
   */
  public function testFalseSendResultIsRetryableFailure(): void {
    $this->useHandler('false');

    $result = $this->channel()->send($this->recipient(), $this->message());

    self::assertFalse($result->success);
    self::assertTrue($result->retryable);
    self::assertSame('EASY_EMAIL_FAILED', $result->errorCode);
  }

  /**
   * A suppressed duplicate (FALSE) maps to success, not a retryable failure.
   *
   * Easy Email's sendEmail() short-circuits to FALSE when a message with the
   * same unique key was already sent; the message is effectively delivered, so
   * the channel must not ask the queue worker to retry it.
   */
  public function testSuppressedDuplicateIsSuccess(): void {
    $this->useHandler('duplicate');

    $result = $this->channel()->send($this->recipient(), $this->message());

    self::assertTrue($result->success);
    self::assertFalse($result->retryable);
  }

  /**
   * A thrown error is caught and mapped to a retryable EASY_EMAIL_EXCEPTION.
   */
  public function testExceptionIsCaughtAsRetryableFailure(): void {
    $this->useHandler('throw');

    $result = $this->channel()->send($this->recipient(), $this->message());

    self::assertFalse($result->success);
    self::assertTrue($result->retryable);
    self::assertSame('EASY_EMAIL_EXCEPTION', $result->errorCode);
  }

}
