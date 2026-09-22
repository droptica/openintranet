<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Lock\LockBackendInterface;

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

  /**
   * Prefix for locks serializing a dedupe claim.
   */
  private const LOCK_PREFIX = 'openintranet_notifications.dedupe.';

  /**
   * Claim tokens owned by this service instance, keyed by dedupe key.
   *
   * @var array<string, string>
   */
  private array $ownedClaims = [];

  public function __construct(
    private readonly KeyValueExpirableFactoryInterface $keyValueExpirableFactory,
    private readonly TimeInterface $time,
    private readonly LockBackendInterface $lock,
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
   * Claims a dedupe key if no unexpired claim exists.
   *
   * The database expirable key-value backend implements
   * setWithExpireIfNotExists() as a separate has()/set() pair. Serialize that
   * check and write with Drupal's lock backend so overlapping requests cannot
   * both win the same key.
   *
   * @param string $key
   *   The dedupe key.
   * @param int $windowSec
   *   The time-to-live in seconds for the claim.
   *
   * @return bool
   *   TRUE when this request claimed the key, FALSE when it was already
   *   claimed or the claim lock could not be acquired.
   */
  public function claim(string $key, int $windowSec): bool {
    $lockName = self::LOCK_PREFIX . $key;
    if (!$this->acquireLock($lockName)) {
      return FALSE;
    }

    try {
      $store = $this->store();
      if ($store->has($key)) {
        return FALSE;
      }

      $token = Crypt::randomBytesBase64();
      $store->setWithExpire($key, $token, $windowSec);
      $this->ownedClaims[$key] = $token;
      return TRUE;
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /**
   * Releases a claim made by this service instance.
   *
   * The stored ownership token prevents a delayed abandon path from deleting a
   * newer request's claim after the original claim has expired.
   *
   * @param string $key
   *   The dedupe key to release.
   */
  public function release(string $key): void {
    if (!isset($this->ownedClaims[$key])) {
      return;
    }

    $lockName = self::LOCK_PREFIX . $key;
    if (!$this->acquireLock($lockName)) {
      return;
    }

    try {
      $token = $this->ownedClaims[$key];
      $store = $this->store();
      $storedToken = $store->get($key);
      if (is_string($storedToken) && hash_equals($token, $storedToken)) {
        $store->delete($key);
      }
      unset($this->ownedClaims[$key]);
    }
    finally {
      $this->lock->release($lockName);
    }
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
   * Acquires a short-lived claim lock, waiting once for a competing claimant.
   */
  private function acquireLock(string $lockName): bool {
    if ($this->lock->acquire($lockName)) {
      return TRUE;
    }

    $this->lock->wait($lockName, 1);
    return $this->lock->acquire($lockName);
  }

  /**
   * The expirable key-value store for dedupe keys.
   */
  private function store(): KeyValueStoreExpirableInterface {
    return $this->keyValueExpirableFactory->get(self::COLLECTION);
  }

}
