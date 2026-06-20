<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications_smsapi\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Channel\NotificationChannelInterface;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;

/**
 * Tests the sms_smsapi channel send path and plugin discovery.
 *
 * The smsapi.service is replaced with a test double so no real SMS / network
 * call is made; the double returns a constructed Sms or NULL on demand.
 *
 * @group openintranet_notifications
 */
final class SmsSmsApiChannelTest extends KernelTestBase {

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
   * Instantiates the channel under test.
   */
  private function channel(): NotificationChannelInterface {
    /** @var \Drupal\openintranet_notifications\Channel\ChannelPluginManager $manager */
    $manager = $this->container->get('plugin.manager.notification_channel');
    $channel = $manager->createInstance('sms_smsapi');
    self::assertInstanceOf(NotificationChannelInterface::class, $channel);
    return $channel;
  }

  /**
   * A phone recipient.
   */
  private function recipient(): NotificationRecipient {
    return new NotificationRecipient(type: 'phone', value: '+48500100200', langcode: 'en');
  }

  /**
   * A successful send returns the provider message id.
   */
  public function testSuccessfulSendReturnsProviderId(): void {
    $this->smsapiDouble->shouldSucceed = TRUE;
    $this->smsapiDouble->smsId = 'PROV-1';

    $result = $this->channel()->send($this->recipient(), new NotificationMessage(subject: 'Subj', body: 'Body'));

    self::assertTrue($result->success);
    self::assertSame('PROV-1', $result->providerMessageId);
    self::assertSame('+48500100200', $this->smsapiDouble->lastSend['phone']);
  }

  /**
   * A NULL result from smsapi classifies as a retryable SMSAPI_NULL failure.
   */
  public function testNullResultIsRetryableFailure(): void {
    $this->smsapiDouble->shouldSucceed = FALSE;

    $result = $this->channel()->send($this->recipient(), new NotificationMessage(subject: 'Subj', body: 'Body'));

    self::assertFalse($result->success);
    self::assertTrue($result->retryable);
    self::assertSame('SMSAPI_NULL', $result->errorCode);
  }

  /**
   * A recipient with no phone is a permanent NO_PHONE failure.
   */
  public function testNoPhoneIsPermanentFailure(): void {
    $recipient = new NotificationRecipient(type: 'phone', value: NULL, langcode: 'en');

    $result = $this->channel()->send($recipient, new NotificationMessage(subject: 'Subj', body: 'Body'));

    self::assertFalse($result->success);
    self::assertFalse($result->retryable);
    self::assertSame('NO_PHONE', $result->errorCode);
  }

  /**
   * Enabling the submodule makes the channel manager discover sms_smsapi.
   */
  public function testChannelIsDiscovered(): void {
    /** @var \Drupal\openintranet_notifications\Channel\ChannelPluginManager $manager */
    $manager = $this->container->get('plugin.manager.notification_channel');
    self::assertTrue($manager->hasDefinition('sms_smsapi'));
  }

}
