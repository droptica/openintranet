<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;

/**
 * Notification-level deduplicator.
 *
 * Suppresses identical notifications within a time window (00-synteza §12).
 * Dedupe is notification-level, never per-channel: a notification is
 * multi-channel, so the key carries no channel component.
 */
final class Deduplicator {

  /**
   * The expirable key-value collection holding dedupe keys.
   */
  private const COLLECTION = 'openintranet_notifications.dedupe';

  public function __construct(
    private readonly KeyValueExpirableFactoryInterface $keyValueExpirableFactory,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Computes a stable dedupe key for a notification.
   *
   * @param string $type
   *   The notification type id.
   * @param string $sourceRef
   *   The source reference (e.g. "node:5").
   * @param string $recipientRef
   *   The recipient reference (e.g. "user:42").
   * @param string $context
   *   Optional extra context disambiguator (e.g. a thread id).
   *
   * @return string
   *   The deterministic sha256 dedupe key.
   */
  public function computeKey(string $type, string $sourceRef, string $recipientRef, string $context = ''): string {
    return hash('sha256', implode(':', [$type, $sourceRef, $recipientRef, $context]));
  }

  /**
   * Whether a notification with this key was already recorded in the window.
   *
   * @param string $key
   *   The dedupe key.
   *
   * @return bool
   *   TRUE when a non-expired entry exists for the key.
   */
  public function isDuplicate(string $key): bool {
    return $this->store()->has($key);
  }

  /**
   * Records a notification key so later identical sends are suppressed.
   *
   * @param string $key
   *   The dedupe key.
   * @param int $windowSec
   *   The time-to-live in seconds for the recorded key.
   */
  public function record(string $key, int $windowSec): void {
    $this->store()->setWithExpire($key, $this->time->getRequestTime(), $windowSec);
  }

  /**
   * The expirable key-value store for dedupe keys.
   */
  private function store(): KeyValueStoreExpirableInterface {
    return $this->keyValueExpirableFactory->get(self::COLLECTION);
  }

}
