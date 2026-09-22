<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Channel;

use Drupal\openintranet_notifications\Dto\NotificationRecipient;

/**
 * Channel contract conformance for the inbox channel.
 *
 * Only internal users have an inbox, so a non-user (email) recipient is
 * unaddressable. The inbox send is a no-op success, so there is no failing
 * scenario.
 *
 * @group openintranet_notifications
 */
final class InboxChannelContractTest extends ChannelContractTestBase {

  /**
   * {@inheritdoc}
   */
  protected function channelId(): string {
    return 'inbox';
  }

  /**
   * {@inheritdoc}
   */
  protected function addressableRecipient(): NotificationRecipient {
    return new NotificationRecipient(type: 'user', id: 1, langcode: 'en');
  }

  /**
   * {@inheritdoc}
   */
  protected function unaddressableRecipient(): ?NotificationRecipient {
    return new NotificationRecipient(type: 'email', value: 'x@y.test', langcode: 'en');
  }

}
