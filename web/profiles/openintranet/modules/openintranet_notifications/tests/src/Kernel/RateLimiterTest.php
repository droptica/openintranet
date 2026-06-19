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

}
