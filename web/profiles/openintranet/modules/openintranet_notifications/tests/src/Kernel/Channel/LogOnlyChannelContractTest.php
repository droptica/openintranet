<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Channel;

/**
 * Channel contract conformance for the log-only channel.
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

}
