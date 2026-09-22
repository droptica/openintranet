<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Unit\Dto;

use Drupal\openintranet_notifications\Dto\NotificationMessage;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\openintranet_notifications\Dto\NotificationMessage
 * @group openintranet_notifications
 */
final class NotificationMessageTest extends TestCase {

  /**
   * The DTO carries subject, body, summary and an arbitrary payload.
   */
  public function testCarriesSubjectBodySummaryAndPayload(): void {
    $message = new NotificationMessage(
      subject: 'Subject',
      body: 'Body',
      summary: 'Short',
      payload: ['key' => 'value'],
    );
    self::assertSame('Subject', $message->subject);
    self::assertSame('Body', $message->body);
    self::assertSame('Short', $message->summary);
    self::assertSame(['key' => 'value'], $message->payload);
  }

  /**
   * Summary and payload default to empty.
   */
  public function testSummaryAndPayloadDefaultToEmpty(): void {
    $message = new NotificationMessage(subject: 'S', body: 'B');
    self::assertSame('', $message->summary);
    self::assertSame([], $message->payload);
  }

  /**
   * A non-empty summary is returned verbatim.
   */
  public function testReturnsSummaryWhenPresent(): void {
    $message = new NotificationMessage(subject: 'S', body: 'Long body text', summary: 'Summary');
    self::assertSame('Summary', $message->getSummaryOrTruncatedBody(4));
  }

  /**
   * When the summary is empty the body is stripped of tags and truncated.
   */
  public function testFallsBackToStrippedTruncatedBodyWhenSummaryEmpty(): void {
    $message = new NotificationMessage(subject: 'S', body: '<p>Hello <strong>world</strong></p>');
    self::assertSame('Hello', $message->getSummaryOrTruncatedBody(5));
  }

}
