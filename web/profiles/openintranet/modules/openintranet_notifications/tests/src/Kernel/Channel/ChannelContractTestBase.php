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
 * regardless of transport, and are pinned by concrete per-channel hooks so the
 * assertions actually bite rather than re-deriving the implementation:
 * - isAvailable() returns a bool;
 * - canSendTo() is TRUE for an addressable recipient and FALSE for an
 *   unaddressable one (channels that address everyone declare no unaddressable
 *   recipient, so that leg is skipped);
 * - send() never throws on a transport error — it classifies the outcome as a
 *   DeliveryResult so the worker decides whether to retry.
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
   * A recipient the channel can address.
   */
  abstract protected function addressableRecipient(): NotificationRecipient;

  /**
   * A recipient the channel cannot address, or NULL when it addresses everyone.
   */
  protected function unaddressableRecipient(): ?NotificationRecipient {
    return NULL;
  }

  /**
   * A scenario that drives the channel's failure branch.
   *
   * @return array{0: \Drupal\openintranet_notifications\Dto\NotificationRecipient, 1: \Drupal\openintranet_notifications\Dto\NotificationMessage}|null
   *   A [recipient, message] tuple whose send() reports a failure, or NULL when
   *   the channel has no failure path (it always succeeds).
   */
  protected function failingScenario(): ?array {
    return NULL;
  }

  /**
   * Instantiates the channel under test.
   */
  protected function createChannel(): NotificationChannelInterface {
    /** @var \Drupal\openintranet_notifications\Channel\ChannelPluginManager $manager */
    $manager = $this->container->get('plugin.manager.openintranet_notification_channel');
    $channel = $manager->createInstance($this->channelId());
    self::assertInstanceOf(NotificationChannelInterface::class, $channel);
    return $channel;
  }

  /**
   * The isAvailable() method returns a boolean.
   */
  public function testIsAvailableReturnsBool(): void {
    self::assertIsBool($this->createChannel()->isAvailable());
  }

  /**
   * The canSendTo() call accepts addressable and rejects unaddressable ones.
   */
  public function testCanSendToMatchesAddressability(): void {
    $channel = $this->createChannel();
    self::assertTrue($channel->canSendTo($this->addressableRecipient()));

    $unaddressable = $this->unaddressableRecipient();
    if ($unaddressable === NULL) {
      // The channel addresses everyone; there is no unaddressable recipient.
      return;
    }
    self::assertFalse($channel->canSendTo($unaddressable));
  }

  /**
   * The send() failure branch returns a DeliveryResult and never throws.
   */
  public function testSendFailureReturnsResultWithoutThrowing(): void {
    $scenario = $this->failingScenario();
    if ($scenario === NULL) {
      // The channel has no failure path; the happy-path send still must yield a
      // DeliveryResult so the no-throw / always-classify invariant is covered.
      $result = $this->createChannel()->send(
        $this->addressableRecipient(),
        new NotificationMessage(subject: 'Contract subject', body: 'Body'),
      );
      self::assertInstanceOf(DeliveryResult::class, $result);
      return;
    }

    [$recipient, $message] = $scenario;
    $result = $this->createChannel()->send($recipient, $message);
    self::assertInstanceOf(DeliveryResult::class, $result);
    self::assertFalse($result->success);
  }

}
