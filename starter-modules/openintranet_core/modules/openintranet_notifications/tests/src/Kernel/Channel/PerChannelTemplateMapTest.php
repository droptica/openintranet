<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Channel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Service\DeliverySender;
use Drupal\openintranet_notifications_test\Plugin\NotificationChannel\BodyRecordingChannel;
use Drupal\user\Entity\User;

/**
 * Tests that template_map renders a channel-specific body at send time (§4.1).
 *
 * The notification stores the channel-agnostic body. When the type maps a
 * channel to its own template, the delivery on that channel must render the
 * mapped template; a channel with no map entry sends the stored default.
 *
 * @group openintranet_notifications
 */
final class PerChannelTemplateMapTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'filter',
    'options',
    'text',
    'token',
    'key',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
    'dynamic_entity_reference',
    'openintranet_notifications',
    'openintranet_notifications_test',
  ];

  /**
   * The delivery sender under test.
   */
  private DeliverySender $sender;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('openintranet_notification');
    $this->installEntitySchema('openintranet_notif_delivery');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'filter', 'openintranet_notifications']);

    $typeStorage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification_type');
    $typeStorage->delete($typeStorage->loadMultiple());

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    User::create(['uid' => 81, 'name' => 'recipient', 'status' => 1])->save();

    $this->sender = $this->container->get('openintranet_notifications.delivery_sender');
  }

  /**
   * A mapped channel renders the channel-specific body; others use the default.
   */
  public function testMappedChannelRendersChannelSpecificBody(): void {
    NotificationType::create([
      'id' => 'mapped_type',
      'label' => 'Mapped',
      'template_renderer' => 'token_text',
      'subject_template' => 'Subject [node:title]',
      'body_template' => 'DEFAULT body [node:title]',
      'template_map' => [
        'body_recording' => 'SHORT [node:title]',
      ],
    ])->save();

    $node = Node::create(['type' => 'article', 'title' => 'Headline', 'uid' => 81]);
    $node->save();

    // Build + persist the notification via the factory (renders the default
    // body once, channel-agnostic).
    $factory = $this->container->get('openintranet_notifications.notification_factory');
    $notification = $factory->create('mapped_type', [
      'uid' => 81,
      'recipient_account' => User::load(81),
      'source_entity' => $node,
    ]);
    $notification->save();
    self::assertSame('DEFAULT body Headline', (string) $notification->get('body')->value, 'The stored body is the channel-agnostic default.');

    // Deliver on the MAPPED channel: the recorded body is the mapped template.
    $this->sender->send($this->delivery($notification, 'body_recording'));
    $recorded = \Drupal::state()->get(BodyRecordingChannel::STATE_KEY);
    self::assertSame('SHORT Headline', $recorded['body'], 'The mapped channel rendered its channel-specific template.');
    // The subject is not mapped, so it stays the stored default.
    self::assertSame('Subject Headline', $recorded['subject'], 'The subject keeps the stored (channel-agnostic) render.');
  }

  /**
   * A channel with no template_map entry sends the stored default body.
   */
  public function testUnmappedChannelUsesStoredDefaultBody(): void {
    NotificationType::create([
      'id' => 'partial_map_type',
      'label' => 'Partial',
      'template_renderer' => 'token_text',
      'subject_template' => 'Subject [node:title]',
      'body_template' => 'DEFAULT body [node:title]',
      // Maps a DIFFERENT channel, so body_recording falls back to the default.
      'template_map' => [
        'some_other_channel' => 'SHORT [node:title]',
      ],
    ])->save();

    $node = Node::create(['type' => 'article', 'title' => 'Headline', 'uid' => 81]);
    $node->save();

    $factory = $this->container->get('openintranet_notifications.notification_factory');
    $notification = $factory->create('partial_map_type', [
      'uid' => 81,
      'recipient_account' => User::load(81),
      'source_entity' => $node,
    ]);
    $notification->save();

    $this->sender->send($this->delivery($notification, 'body_recording'));
    $recorded = \Drupal::state()->get(BodyRecordingChannel::STATE_KEY);
    self::assertSame('DEFAULT body Headline', $recorded['body'], 'An unmapped channel sends the stored default body.');
  }

  /**
   * Builds and saves a delivery row for a notification on a channel.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface $notification
   *   The parent notification.
   * @param string $channelId
   *   The channel plugin id.
   *
   * @return \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface
   *   The saved pending delivery.
   */
  private function delivery(NotificationInterface $notification, string $channelId): NotificationDeliveryInterface {
    $queue = $this->container->get('openintranet_notifications.delivery_queue');
    $recipient = NotificationRecipient::forUserId(81, User::load(81));
    $delivery = $queue->createDeliveryRow($notification, $recipient, $channelId);
    $delivery->save();
    return $delivery;
  }

}
