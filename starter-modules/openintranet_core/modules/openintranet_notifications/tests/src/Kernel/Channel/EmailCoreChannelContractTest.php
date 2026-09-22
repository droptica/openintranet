<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Channel;

use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\user\Entity\User;

/**
 * Channel contract conformance for the email_core channel.
 *
 * The channel addresses a user with a mail and rejects an email-type recipient
 * with no value; it always classifies its outcome, so it declares no failing
 * scenario (the base happy-path leg covers the no-throw invariant).
 *
 * @group openintranet_notifications
 */
final class EmailCoreChannelContractTest extends ChannelContractTestBase {

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
   * {@inheritdoc}
   */
  protected function channelId(): string {
    return 'email_core';
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
    return new NotificationRecipient(type: 'email', value: NULL, langcode: 'en');
  }

}
