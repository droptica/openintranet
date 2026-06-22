<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Unit;

use Drupal\openintranet_notifications\QuietHours;
use PHPUnit\Framework\TestCase;

/**
 * Tests the pure quiet-hours window math.
 *
 * @coversDefaultClass \Drupal\openintranet_notifications\QuietHours
 * @group openintranet_notifications
 */
final class QuietHoursTest extends TestCase {

  /**
   * Builds a unix timestamp for a wall-clock time in a timezone.
   */
  private static function ts(string $iso, string $tz): int {
    return (new \DateTime($iso, new \DateTimeZone($tz)))->getTimestamp();
  }

  /**
   * A same-day window (09:00-17:00) contains noon but not the evening.
   *
   * @covers ::isWithin
   */
  public function testSameDayWindow(): void {
    $window = ['start' => '09:00', 'end' => '17:00', 'tz' => 'Europe/Warsaw'];

    self::assertTrue(QuietHours::isWithin($window, self::ts('2026-06-22 12:00', 'Europe/Warsaw')));
    self::assertFalse(QuietHours::isWithin($window, self::ts('2026-06-22 08:59', 'Europe/Warsaw')));
    self::assertFalse(QuietHours::isWithin($window, self::ts('2026-06-22 17:00', 'Europe/Warsaw')));
    self::assertFalse(QuietHours::isWithin($window, self::ts('2026-06-22 21:00', 'Europe/Warsaw')));
  }

  /**
   * A midnight-crossing window (22:00-07:00) covers both sides of midnight.
   *
   * @covers ::isWithin
   */
  public function testMidnightCrossingWindow(): void {
    $window = ['start' => '22:00', 'end' => '07:00', 'tz' => 'Europe/Warsaw'];

    // Late evening side.
    self::assertTrue(QuietHours::isWithin($window, self::ts('2026-06-22 23:30', 'Europe/Warsaw')));
    self::assertTrue(QuietHours::isWithin($window, self::ts('2026-06-22 22:00', 'Europe/Warsaw')));
    // Early morning side.
    self::assertTrue(QuietHours::isWithin($window, self::ts('2026-06-22 03:00', 'Europe/Warsaw')));
    self::assertTrue(QuietHours::isWithin($window, self::ts('2026-06-22 06:59', 'Europe/Warsaw')));
    // Outside.
    self::assertFalse(QuietHours::isWithin($window, self::ts('2026-06-22 12:00', 'Europe/Warsaw')));
    self::assertFalse(QuietHours::isWithin($window, self::ts('2026-06-22 07:00', 'Europe/Warsaw')));
    self::assertFalse(QuietHours::isWithin($window, self::ts('2026-06-22 21:59', 'Europe/Warsaw')));
  }

  /**
   * The timezone shifts the wall-clock the window is evaluated against.
   *
   * 06:00 UTC is 08:00 in Europe/Warsaw (UTC+2 in summer): inside a 07:00-09:00
   * Warsaw window, but outside the same window read in UTC.
   *
   * @covers ::isWithin
   */
  public function testTimezoneHandling(): void {
    $instant = self::ts('2026-06-22 06:00', 'UTC');

    self::assertTrue(QuietHours::isWithin(['start' => '07:00', 'end' => '09:00', 'tz' => 'Europe/Warsaw'], $instant));
    self::assertFalse(QuietHours::isWithin(['start' => '07:00', 'end' => '09:00', 'tz' => 'UTC'], $instant));
  }

  /**
   * Missing/empty bounds or an equal start/end mean no quiet window.
   *
   * @covers ::isWithin
   */
  public function testNoWindow(): void {
    $now = self::ts('2026-06-22 12:00', 'UTC');

    self::assertFalse(QuietHours::isWithin([], $now));
    self::assertFalse(QuietHours::isWithin(['start' => '', 'end' => '17:00', 'tz' => 'UTC'], $now));
    self::assertFalse(QuietHours::isWithin(['start' => '09:00', 'end' => '', 'tz' => 'UTC'], $now));
    self::assertFalse(QuietHours::isWithin(['start' => NULL, 'end' => NULL, 'tz' => 'UTC'], $now));
    // An equal start and end means no quiet window.
    self::assertFalse(QuietHours::isWithin(['start' => '09:00', 'end' => '09:00', 'tz' => 'UTC'], $now));
  }

  /**
   * Seconds-until-end returns the delay until end inside a same-day window.
   *
   * @covers ::secondsUntilEnd
   */
  public function testSecondsUntilEndSameDay(): void {
    $window = ['start' => '09:00', 'end' => '17:00', 'tz' => 'Europe/Warsaw'];
    // From 12:00 to 17:00 is 5 hours.
    self::assertSame(5 * 3600, QuietHours::secondsUntilEnd($window, self::ts('2026-06-22 12:00', 'Europe/Warsaw')));
  }

  /**
   * Seconds-until-end handles a window crossing midnight on both sides.
   *
   * @covers ::secondsUntilEnd
   */
  public function testSecondsUntilEndMidnightCrossing(): void {
    $window = ['start' => '22:00', 'end' => '07:00', 'tz' => 'Europe/Warsaw'];

    // Evening side: 23:30 -> next 07:00 is 7h30m away.
    self::assertSame(
      (7 * 3600 + 30 * 60),
      QuietHours::secondsUntilEnd($window, self::ts('2026-06-22 23:30', 'Europe/Warsaw')),
    );
    // Morning side: 03:00 -> same-day 07:00 is 4h away.
    self::assertSame(
      4 * 3600,
      QuietHours::secondsUntilEnd($window, self::ts('2026-06-22 03:00', 'Europe/Warsaw')),
    );
  }

  /**
   * Seconds-until-end is measured in real elapsed seconds across a DST change.
   *
   * A 22:00-07:00 Europe/Warsaw window, evaluated at 23:30 local on the evening
   * leading into a clock change, must land on the true next 07:00 local — the
   * wall-clock minutes-of-day math would always say 7h30m (27000s), but the
   * real elapsed time is shorter on the spring-forward night (the clock skips
   * an hour: 6h30m = 23400s) and longer on the fall-back night (the clock
   * repeats an hour: 8h30m = 30600s).
   *
   * @covers ::secondsUntilEnd
   */
  public function testSecondsUntilEndAcrossDstChange(): void {
    $window = ['start' => '22:00', 'end' => '07:00', 'tz' => 'Europe/Warsaw'];

    // Spring forward (2026-03-29 02:00 -> 03:00): the night loses an hour, so
    // 23:30 -> next 07:00 is 6h30m of real time, not 7h30m.
    self::assertSame(
      6 * 3600 + 30 * 60,
      QuietHours::secondsUntilEnd($window, self::ts('2026-03-28 23:30', 'Europe/Warsaw')),
    );

    // Fall back (2026-10-25 03:00 -> 02:00): the night gains an hour, so
    // 23:30 -> next 07:00 is 8h30m of real time.
    self::assertSame(
      8 * 3600 + 30 * 60,
      QuietHours::secondsUntilEnd($window, self::ts('2026-10-24 23:30', 'Europe/Warsaw')),
    );
  }

  /**
   * Seconds-until-end lands on the HH:MM:00 boundary (no sub-minute drift).
   *
   * @covers ::secondsUntilEnd
   */
  public function testSecondsUntilEndLandsOnMinuteBoundary(): void {
    $window = ['start' => '09:00', 'end' => '17:00', 'tz' => 'Europe/Warsaw'];
    // From 12:30:37 to 17:00:00 is 4h 29m 23s; the elapsed 37s are not rounded
    // away into the next minute.
    self::assertSame(
      4 * 3600 + 29 * 60 + 23,
      QuietHours::secondsUntilEnd($window, self::ts('2026-06-22 12:30:37', 'Europe/Warsaw')),
    );
  }

  /**
   * Seconds-until-end is 0 when not currently inside the quiet window.
   *
   * @covers ::secondsUntilEnd
   */
  public function testSecondsUntilEndOutsideIsZero(): void {
    $window = ['start' => '22:00', 'end' => '07:00', 'tz' => 'Europe/Warsaw'];
    self::assertSame(0, QuietHours::secondsUntilEnd($window, self::ts('2026-06-22 12:00', 'Europe/Warsaw')));

    // No window at all is also 0.
    self::assertSame(0, QuietHours::secondsUntilEnd([], self::ts('2026-06-22 12:00', 'UTC')));
  }

}
