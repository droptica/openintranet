<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Channel;

use Drupal\openintranet_notifications\Dto\NotificationRecipient;

/**
 * Channel contract conformance for the log-only channel.
 *
 * The log-only channel addresses every recipient and always succeeds, so it
 * declares no unaddressable recipient and no failing scenario (defaults).
 *
 * @group openintranet_notifications
 */
final class LogOnlyChannelContractTest extends ChannelContractTestBase {

  /**
   * {@inheritdoc}
   */
  protected function channelId(): string {
    return 'log_only';
  }

  /**
   * {@inheritdoc}
   */
  protected function addressableRecipient(): NotificationRecipient {
    return new NotificationRecipient(type: 'user', id: 1, langcode: 'en');
  }

}
