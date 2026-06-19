<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Channel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Channel\NotificationChannelInterface;
use Drupal\openintranet_notifications\Dto\DeliveryResult;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;

/**
 * Universal contract every notification channel must satisfy.
 *
 * Stage 3 channels MUST extend this base — it is the §14 "contract test per
 * channel" harness. The invariants asserted here hold for ANY channel,
 * regardless of transport:
 * - send() ALWAYS returns a DeliveryResult and never throws on a transport
 *   error (the worker classifies the outcome, the channel does not bubble up);
 * - isAvailable() returns a bool;
 * - canSendTo() agrees with address presence (getRecipientAddress() !== NULL).
 *
 * @group openintranet_notifications
 */
abstract class ChannelContractTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'openintranet_notifications',
    'dynamic_entity_reference',
    'options',
    'token',
    'key',
    // eca:eca depends on modeler_api:modeler_api (ECA 3.1.x); without it the
    // eca.processor service references a non-existent template_token_resolver.
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
  ];

  /**
   * The plugin id of the channel under test.
   */
  abstract protected function channelId(): string;

  /**
   * Instantiates the channel under test.
   */
  protected function createChannel(): NotificationChannelInterface {
    /** @var \Drupal\openintranet_notifications\Channel\ChannelPluginManager $manager */
    $manager = $this->container->get('plugin.manager.notification_channel');
    $channel = $manager->createInstance($this->channelId());
    self::assertInstanceOf(NotificationChannelInterface::class, $channel);
    return $channel;
  }

  /**
   * A representative user recipient for contract assertions.
   */
  protected function recipient(): NotificationRecipient {
    return new NotificationRecipient(type: 'user', id: 1, langcode: 'en');
  }

  /**
   * The isAvailable() method returns a boolean.
   */
  public function testIsAvailableReturnsBool(): void {
    self::assertIsBool($this->createChannel()->isAvailable());
  }

  /**
   * The canSendTo() method agrees with getRecipientAddress() being non-NULL.
   */
  public function testCanSendToAgreesWithAddressPresence(): void {
    $channel = $this->createChannel();
    $recipient = $this->recipient();
    $hasAddress = $channel->getRecipientAddress($recipient) !== NULL;
    self::assertSame($hasAddress, $channel->canSendTo($recipient));
  }

  /**
   * The send() method always returns a DeliveryResult and never throws.
   */
  public function testSendAlwaysReturnsDeliveryResult(): void {
    $channel = $this->createChannel();
    $message = new NotificationMessage(subject: 'Contract subject', body: 'Body');
    $result = $channel->send($this->recipient(), $message);
    self::assertInstanceOf(DeliveryResult::class, $result);
  }

}
