<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Unit\Service;

use Drupal\openintranet_notifications\Service\AuditLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Tests PII masking in the audit logger.
 *
 * @group openintranet_notifications
 */
final class AuditLoggerMaskTest extends TestCase {

  /**
   * The audit logger under test.
   */
  private AuditLogger $auditLogger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->auditLogger = new AuditLogger(new NullLogger());
  }

  /**
   * Emails keep only the first local and first domain character.
   */
  public function testMasksEmail(): void {
    self::assertSame('j***@e***.com', $this->auditLogger->maskPii('john@example.com'));
  }

  /**
   * Phone numbers keep the first character and the last three digits.
   */
  public function testMasksPhone(): void {
    self::assertSame('+48***200', $this->auditLogger->maskPii('+48555100200'));
  }

  /**
   * A non-PII value passes through unchanged.
   */
  public function testLeavesPlainValueUntouched(): void {
    self::assertSame('inbox', $this->auditLogger->maskPii('inbox'));
  }

  /**
   * An empty value masks to an empty string.
   */
  public function testEmptyValue(): void {
    self::assertSame('', $this->auditLogger->maskPii(''));
  }

}
