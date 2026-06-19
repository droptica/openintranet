<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Unit\Dto;

use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\openintranet_notifications\Dto\NotificationRecipient
 * @group openintranet_notifications
 */
final class NotificationRecipientTest extends TestCase {

  /**
   * Per 00-synteza §8: the DTO carries IDENTITY; the channel resolves address.
   */
  public function testUserRecipientCarriesIdentityNotAddresses(): void {
    $r = new NotificationRecipient(type: 'user', id: 42, langcode: 'pl');
    self::assertSame('user', $r->type);
    self::assertSame(42, $r->id);
    self::assertTrue($r->isUser());
    self::assertSame('pl', $r->langcode);
  }

  /**
   * External recipients carry a raw contact value and default to English.
   */
  public function testExternalRecipientCarriesRawValue(): void {
    $r = new NotificationRecipient(type: 'email', value: 'x@y.test');
    self::assertFalse($r->isUser());
    self::assertSame('x@y.test', $r->value);
    self::assertSame('en', $r->langcode);
  }

}
