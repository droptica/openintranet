<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;

/**
 * Per-(user, channel, type) rate limiter.
 *
 * Caps how many notifications a recipient may receive on a channel for a type
 * within a rolling window (00-synteza §12). Counters expire with the window.
 */
final class RateLimiter {

  /**
   * The expirable key-value collection holding the counters.
   */
  private const COLLECTION = 'openintranet_notifications.rate_limit';

  public function __construct(
    private readonly KeyValueExpirableFactoryInterface $keyValueExpirableFactory,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Whether a send is allowed under the limit, counting this attempt.
   *
   * @param int $uid
   *   The recipient user id.
   * @param string $channel
   *   The channel plugin id.
   * @param string $type
   *   The notification type id.
   * @param int $limit
   *   The maximum number of sends allowed within the window.
   * @param int $windowSec
   *   The rolling window length in seconds.
   *
   * @return bool
   *   TRUE when the count after this attempt is within the limit.
   */
  public function allow(int $uid, string $channel, string $type, int $limit, int $windowSec): bool {
    $store = $this->store();
    $key = "$uid:$channel:$type";
    $count = (int) $store->get($key, 0) + 1;
    $store->setWithExpire($key, $count, $windowSec);
    return $count <= $limit;
  }

  /**
   * The expirable key-value store for the counters.
   */
  private function store(): KeyValueStoreExpirableInterface {
    return $this->keyValueExpirableFactory->get(self::COLLECTION);
  }

}
