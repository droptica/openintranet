<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Unit\Dto;

use Drupal\openintranet_notifications\Dto\DeliveryResult;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\openintranet_notifications\Dto\DeliveryResult
 * @group openintranet_notifications
 */
final class DeliveryResultTest extends TestCase {

  /**
   * A retryable failure is unsuccessful, retryable and carries the error code.
   */
  public function testRetryableFailureIsRetryable(): void {
    $r = DeliveryResult::retryableFailure('HTTP_503', 'upstream down');
    self::assertFalse($r->success);
    self::assertTrue($r->retryable);
    self::assertSame('HTTP_503', $r->errorCode);
  }

  /**
   * A permanent failure is not retryable.
   */
  public function testPermanentFailureNotRetryable(): void {
    self::assertFalse(DeliveryResult::permanentFailure('HTTP_400', 'bad')->retryable);
  }

  /**
   * A success carries the provider message id.
   */
  public function testSuccessCarriesProviderId(): void {
    $r = DeliveryResult::success('msg-1');
    self::assertTrue($r->success);
    self::assertSame('msg-1', $r->providerMessageId);
  }

}
