<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Service\RateLimiter;

/**
 * Tests the per-(user, channel, type) rate limiter service.
 *
 * @group openintranet_notifications
 */
final class RateLimiterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'openintranet_notifications',
    'dynamic_entity_reference',
    'options',
    'text',
    'token',
    'key',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
  ];

  /**
   * The rate limiter under test.
   */
  private RateLimiter $rateLimiter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->rateLimiter = $this->container->get('openintranet_notifications.rate_limiter');
  }

  /**
   * The limiter allows up to the limit, then denies within the window.
   */
  public function testAllowsUpToLimitThenDenies(): void {
    self::assertTrue($this->rateLimiter->allow(42, 'email_core', 'new_article', 2, 3600));
    self::assertTrue($this->rateLimiter->allow(42, 'email_core', 'new_article', 2, 3600));
    self::assertFalse($this->rateLimiter->allow(42, 'email_core', 'new_article', 2, 3600));
  }

  /**
   * Denied attempts do not write or extend the counter (no lockout creep).
   */
  public function testDeniedAttemptsDoNotExtendWindow(): void {
    // Fill the tuple to its limit of 2.
    self::assertTrue($this->rateLimiter->allow(42, 'email_core', 'new_article', 2, 3600));
    self::assertTrue($this->rateLimiter->allow(42, 'email_core', 'new_article', 2, 3600));

    // Several denied attempts must not bump the stored count past the limit.
    self::assertFalse($this->rateLimiter->allow(42, 'email_core', 'new_article', 2, 3600));
    self::assertFalse($this->rateLimiter->allow(42, 'email_core', 'new_article', 2, 3600));
    self::assertFalse($this->rateLimiter->allow(42, 'email_core', 'new_article', 2, 3600));

    $store = $this->container->get('keyvalue.expirable')
      ->get('openintranet_notifications.rate_limit');
    self::assertSame(2, $store->get('42:email_core:new_article'));
  }

  /**
   * Distinct (user, channel, type) tuples are counted independently.
   */
  public function testDistinctTuplesAreIndependent(): void {
    self::assertTrue($this->rateLimiter->allow(42, 'email_core', 'new_article', 1, 3600));
    // Same user, different channel: own counter, still allowed.
    self::assertTrue($this->rateLimiter->allow(42, 'inbox', 'new_article', 1, 3600));
    // Same user/channel, different type: own counter, still allowed.
    self::assertTrue($this->rateLimiter->allow(42, 'email_core', 'new_comment', 1, 3600));
    // Different user, same channel/type: own counter, still allowed.
    self::assertTrue($this->rateLimiter->allow(43, 'email_core', 'new_article', 1, 3600));
    // Original tuple is now over its limit of 1.
    self::assertFalse($this->rateLimiter->allow(42, 'email_core', 'new_article', 1, 3600));
  }

  /**
   * The per-dispatch and per-channel counters are independent key spaces.
   *
   * The dispatcher caps notifications per (uid, type) on the reserved
   * ALL_CHANNELS sentinel; the ECA condition caps sends per (uid, channel,
   * type) on a real channel id. They MUST be separate counters: filling the
   * per-dispatch counter must not throttle a per-channel check and vice versa
   * (no silent disjoint-keyspace surprise — they are independent BY DESIGN).
   */
  public function testPerDispatchAndPerChannelCountersAreIndependent(): void {
    // Fill the dispatcher's per-(uid, type) counter on the sentinel channel.
    self::assertTrue($this->rateLimiter->allow(42, RateLimiter::ALL_CHANNELS, 'new_article', 1, 3600));
    self::assertFalse($this->rateLimiter->allow(42, RateLimiter::ALL_CHANNELS, 'new_article', 1, 3600));

    // The ECA condition's per-channel peek for the same uid/type is unaffected:
    // it reads a separate (uid, channel, type) counter that is still empty.
    self::assertTrue($this->rateLimiter->isWithinLimit(42, 'email_core', 'new_article', 1));
    self::assertTrue($this->rateLimiter->isWithinLimit(42, 'inbox', 'new_article', 1));

    // Conversely, the sentinel is a reserved id distinct from every channel:
    // recording a real-channel send does not bump the per-dispatch counter.
    self::assertTrue($this->rateLimiter->allow(43, 'email_core', 'new_article', 1, 3600));
    self::assertTrue($this->rateLimiter->allow(43, RateLimiter::ALL_CHANNELS, 'new_article', 1, 3600));
  }

  /**
   * Recording a tick increments the per-channel counter the peek reads.
   *
   * The per-(user, channel, type) dimension (§8) is exposed only through the
   * below_rate_limit ECA peek; record() is the unconditional tick the
   * dispatcher fires per created delivery so that peek reflects real usage.
   * Unlike allow(), record() never refuses — it has no limit and only bumps
   * the counter — so it cannot itself throttle a send. After N records the
   * peek at limit N is false.
   */
  public function testRecordIncrementsCounterUnconditionally(): void {
    $this->rateLimiter->record(42, 'email_core', 'new_article', 3600);
    $this->rateLimiter->record(42, 'email_core', 'new_article', 3600);

    // Two ticks recorded: the peek at limit 2 is at-limit, at limit 3 is below.
    self::assertFalse($this->rateLimiter->isWithinLimit(42, 'email_core', 'new_article', 2));
    self::assertTrue($this->rateLimiter->isWithinLimit(42, 'email_core', 'new_article', 3));

    $store = $this->container->get('keyvalue.expirable')
      ->get('openintranet_notifications.rate_limit');
    self::assertSame(2, $store->get('42:email_core:new_article'));

    // A different channel is an independent counter, untouched above.
    self::assertTrue($this->rateLimiter->isWithinLimit(42, 'inbox', 'new_article', 1));
  }

  /**
   * The peek reads the current count without consuming budget.
   */
  public function testIsWithinLimitIsNonMutating(): void {
    // No hits recorded yet: a fresh tuple is within any positive limit.
    self::assertTrue($this->rateLimiter->isWithinLimit(42, 'email_core', 'new_article', 2));

    // Record two hits, filling the limit of 2.
    self::assertTrue($this->rateLimiter->allow(42, 'email_core', 'new_article', 2, 3600));
    self::assertTrue($this->rateLimiter->allow(42, 'email_core', 'new_article', 2, 3600));

    // The peek reports at-limit and, crucially, does not bump the counter.
    self::assertFalse($this->rateLimiter->isWithinLimit(42, 'email_core', 'new_article', 2));
    self::assertFalse($this->rateLimiter->isWithinLimit(42, 'email_core', 'new_article', 2));

    $store = $this->container->get('keyvalue.expirable')
      ->get('openintranet_notifications.rate_limit');
    self::assertSame(2, $store->get('42:email_core:new_article'));

    // A subsequent allow() still has exactly one slot consumed (count 2),
    // confirming the peeks never touched the stored value.
    self::assertFalse($this->rateLimiter->allow(42, 'email_core', 'new_article', 2, 3600));
  }

}
