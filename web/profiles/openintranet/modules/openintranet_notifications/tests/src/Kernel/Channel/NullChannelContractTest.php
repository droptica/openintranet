<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Channel;

/**
 * Channel contract conformance for the null channel.
 *
 * @group openintranet_notifications
 */
final class NullChannelContractTest extends ChannelContractTestBase {

  /**
   * {@inheritdoc}
   */
  protected function channelId(): string {
    return 'null';
  }

}
