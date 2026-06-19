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
   * A denied attempt does not write or extend the counter, so sustained traffic
   * against a throttled tuple cannot keep re-extending the TTL into a permanent
   * lockout — the window still expires on schedule.
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
   *   TRUE when this attempt is within the limit.
   *
   * @todo Stage 2: store the window-end for a precise fixed window when real
   *   per-type limits are wired, instead of re-arming the TTL on each allow.
   */
  public function allow(int $uid, string $channel, string $type, int $limit, int $windowSec): bool {
    $store = $this->store();
    $key = "$uid:$channel:$type";
    $count = (int) $store->get($key, 0);
    if ($count >= $limit) {
      return FALSE;
    }
    $store->setWithExpire($key, $count + 1, $windowSec);
    return TRUE;
  }

  /**
   * Whether the tuple is currently below the limit, without consuming budget.
   *
   * A non-mutating peek: it reads the stored count but never increments it or
   * re-arms the TTL, so it is safe to call from a side-effect-free context such
   * as an ECA condition.
   *
   * @param int $uid
   *   The recipient user id.
   * @param string $channel
   *   The channel plugin id.
   * @param string $type
   *   The notification type id.
   * @param int $limit
   *   The maximum number of sends allowed within the window.
   *
   * @return bool
   *   TRUE when the current count is below the limit.
   */
  public function isWithinLimit(int $uid, string $channel, string $type, int $limit): bool {
    $count = (int) $this->store()->get("$uid:$channel:$type", 0);
    return $count < $limit;
  }

  /**
   * The expirable key-value store for the counters.
   */
  private function store(): KeyValueStoreExpirableInterface {
    return $this->keyValueExpirableFactory->get(self::COLLECTION);
  }

}
