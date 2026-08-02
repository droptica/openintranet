<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Unit\Dto;

use Drupal\Core\Session\AccountInterface;
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

  /**
   * The forUser() factory takes identity + langcode from the loaded account.
   *
   * @covers ::forUser
   */
  public function testForUserBuildsFromAccount(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(7);
    $account->method('getPreferredLangcode')->willReturn('pl');

    $r = NotificationRecipient::forUser($account);

    self::assertSame('user', $r->type);
    self::assertSame(7, $r->id);
    self::assertSame('pl', $r->langcode);
    self::assertSame($account, $r->account);
    self::assertTrue($r->isUser());
  }

  /**
   * The forUserId() factory uses the account langcode when present.
   *
   * @covers ::forUserId
   */
  public function testForUserIdUsesAccountLangcode(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('getPreferredLangcode')->willReturn('de');

    $r = NotificationRecipient::forUserId(9, $account);

    self::assertSame(9, $r->id);
    self::assertSame('de', $r->langcode);
    self::assertSame($account, $r->account);
  }

  /**
   * The forUserId() factory falls back to English without a loaded account.
   *
   * @covers ::forUserId
   */
  public function testForUserIdFallsBackToEnglishWithoutAccount(): void {
    $r = NotificationRecipient::forUserId(9);

    self::assertSame('user', $r->type);
    self::assertSame(9, $r->id);
    self::assertSame('en', $r->langcode);
    self::assertNull($r->account);
    self::assertTrue($r->isUser());
  }

}
