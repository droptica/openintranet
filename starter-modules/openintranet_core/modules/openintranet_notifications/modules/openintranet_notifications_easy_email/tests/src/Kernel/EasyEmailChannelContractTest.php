<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications_easy_email\Kernel;

use Drupal\Tests\openintranet_notifications\Kernel\Channel\ChannelContractTestBase;
use Drupal\easy_email\Entity\EasyEmailType;
use Drupal\openintranet_notifications\Channel\NotificationChannelInterface;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;

/**
 * Channel contract conformance for the Easy Email channel.
 *
 * The channel addresses email-type and user recipients, so a phone recipient is
 * unaddressable. The failing scenario sends to that unaddressable recipient so
 * send() must classify NO_ADDRESS without throwing. The channel is instantiated
 * with a real email_type so isAvailable() and canSendTo() actually bite.
 *
 * @group openintranet_notifications
 */
final class EasyEmailChannelContractTest extends ChannelContractTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'filter',
    'file',
    'text',
    'easy_email',
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
    $this->installEntitySchema('easy_email');
    EasyEmailType::create([
      'id' => self::EMAIL_TYPE,
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
   * {@inheritdoc}
   */
  protected function createChannel(): NotificationChannelInterface {
    /** @var \Drupal\openintranet_notifications\Channel\ChannelPluginManager $manager */
    $manager = $this->container->get('plugin.manager.openintranet_notification_channel');
    $channel = $manager->createInstance($this->channelId(), ['email_type' => self::EMAIL_TYPE]);
    self::assertInstanceOf(NotificationChannelInterface::class, $channel);
    return $channel;
  }

  /**
   * {@inheritdoc}
   */
  protected function channelId(): string {
    return 'email_easy_email';
  }

  /**
   * {@inheritdoc}
   */
  protected function addressableRecipient(): NotificationRecipient {
    return new NotificationRecipient(type: 'email', value: 'rcpt@example.com', langcode: 'en');
  }

  /**
   * {@inheritdoc}
   */
  protected function unaddressableRecipient(): ?NotificationRecipient {
    return new NotificationRecipient(type: 'phone', value: '+48123456789', langcode: 'en');
  }

  /**
   * {@inheritdoc}
   */
  protected function failingScenario(): ?array {
    return [
      $this->unaddressableRecipient(),
      new NotificationMessage(subject: 'Contract subject', body: 'Body'),
    ];
  }

}
