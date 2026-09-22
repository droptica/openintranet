<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications_smsapi\Kernel;

use Drupal\Tests\openintranet_notifications\Kernel\Channel\ChannelContractTestBase;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\user\Entity\User;

/**
 * Channel contract conformance for the sms_smsapi channel.
 *
 * Addresses a phone-type recipient by its value and rejects a user with no
 * phone field; the failing scenario drives the smsapi double to return NULL so
 * send() reports a (non-throwing) failure.
 *
 * @group openintranet_notifications
 */
final class SmsSmsApiChannelContractTest extends ChannelContractTestBase {

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
    'smsapi',
    'openintranet_notifications_smsapi',
  ];

  /**
   * The smsapi service double registered in place of smsapi.service.
   */
  private SmsApiServiceDouble $smsapiDouble;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->smsapiDouble = new SmsApiServiceDouble();
    $this->container->set('smsapi.service', $this->smsapiDouble);
  }

  /**
   * {@inheritdoc}
   */
  protected function channelId(): string {
    return 'sms_smsapi';
  }

  /**
   * {@inheritdoc}
   */
  protected function addressableRecipient(): NotificationRecipient {
    return new NotificationRecipient(type: 'phone', value: '+48500100200', langcode: 'en');
  }

  /**
   * {@inheritdoc}
   */
  protected function unaddressableRecipient(): ?NotificationRecipient {
    $user = User::create([
      'name' => 'nophone',
      'mail' => 'nophone@example.com',
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
  protected function failingScenario(): ?array {
    $this->smsapiDouble->shouldSucceed = FALSE;
    return [
      $this->addressableRecipient(),
      new NotificationMessage(subject: 'Subj', body: 'Body'),
    ];
  }

}
