<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications;

/**
 * Pure quiet-hours window math (no container deps; unit-testable).
 *
 * A window is {start: 'HH:MM', end: 'HH:MM', tz: ?string} as returned by
 * PreferenceResolver::getQuietHours(). The window is evaluated in its timezone
 * (UTC fallback) and may cross midnight (e.g. 22:00-07:00). A window where
 * start === end is treated as no quiet window. The caller passes the current
 * unix timestamp in, so the class never reads the clock itself.
 */
final class QuietHours {

  /**
   * Whether the given instant falls inside the daily quiet window.
   *
   * @param array{start?: ?string, end?: ?string, tz?: ?string} $quietHours
   *   The quiet-hours window.
   * @param int $now
   *   The current unix timestamp.
   *
   * @return bool
   *   TRUE when the window is configured and $now is inside it.
   */
  public static function isWithin(array $quietHours, int $now): bool {
    $bounds = self::bounds($quietHours, $now);
    if ($bounds === NULL) {
      return FALSE;
    }
    [$minutesNow, $startMinutes, $endMinutes] = $bounds;

    if ($startMinutes < $endMinutes) {
      // Same-day window: [start, end).
      return $minutesNow >= $startMinutes && $minutesNow < $endMinutes;
    }
    // Midnight-crossing window: [start, 24:00) ∪ [00:00, end).
    return $minutesNow >= $startMinutes || $minutesNow < $endMinutes;
  }

  /**
   * Seconds from $now until the next occurrence of the window's end.
   *
   * Computed from real \DateTime timestamps in the window timezone, so the
   * delay is correct in elapsed seconds across a DST change and always lands on
   * the HH:MM:00 boundary (no wall-clock minute math, no sub-minute drift).
   *
   * @param array{start?: ?string, end?: ?string, tz?: ?string} $quietHours
   *   The quiet-hours window.
   * @param int $now
   *   The current unix timestamp.
   *
   * @return int
   *   A positive delay until the quiet window ends, or 0 when $now is not
   *   currently inside the window.
   */
  public static function secondsUntilEnd(array $quietHours, int $now): int {
    if (!self::isWithin($quietHours, $now)) {
      return 0;
    }
    $bounds = self::bounds($quietHours, $now);
    // bounds() is non-NULL here: isWithin() returned TRUE.
    \assert($bounds !== NULL);
    [$minutesNow, , $endMinutes] = $bounds;

    $tz = self::timezone($quietHours);
    $endH = \intdiv($endMinutes, 60);
    $endM = $endMinutes % 60;

    // The next wall-clock occurrence of the end HH:MM in the window timezone:
    // today when the end time-of-day is still ahead of now, else tomorrow.
    $endDt = (new \DateTime('@' . $now))->setTimezone($tz);
    if ($endMinutes <= $minutesNow) {
      $endDt->modify('+1 day');
    }
    $endDt->setTime($endH, $endM, 0);

    return $endDt->getTimestamp() - $now;
  }

  /**
   * Normalises the window to minutes-of-day, or NULL when there is no window.
   *
   * @return array{0: int, 1: int, 2: int}|null
   *   [minutesNow, startMinutes, endMinutes], or NULL.
   */
  private static function bounds(array $quietHours, int $now): ?array {
    $start = $quietHours['start'] ?? NULL;
    $end = $quietHours['end'] ?? NULL;
    if ($start === NULL || $end === NULL || $start === '' || $end === '') {
      return NULL;
    }

    $startMinutes = self::toMinutes((string) $start);
    $endMinutes = self::toMinutes((string) $end);
    if ($startMinutes === NULL || $endMinutes === NULL || $startMinutes === $endMinutes) {
      // An equal start and end is a zero-length window: no quiet hours.
      return NULL;
    }

    $local = (new \DateTime('@' . $now))->setTimezone(self::timezone($quietHours));
    $minutesNow = ((int) $local->format('G')) * 60 + (int) $local->format('i');

    return [$minutesNow, $startMinutes, $endMinutes];
  }

  /**
   * Resolves the window timezone, falling back to UTC.
   */
  private static function timezone(array $quietHours): \DateTimeZone {
    $tz = $quietHours['tz'] ?? NULL;
    if (\is_string($tz) && $tz !== '') {
      try {
        return new \DateTimeZone($tz);
      }
      catch (\Exception) {
        // Fall through to UTC on an invalid identifier.
      }
    }
    return new \DateTimeZone('UTC');
  }

  /**
   * Parses an 'HH:MM' string into minutes-of-day, or NULL when malformed.
   */
  private static function toMinutes(string $time): ?int {
    if (\preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m) !== 1) {
      return NULL;
    }
    $hours = (int) $m[1];
    $minutes = (int) $m[2];
    if ($hours > 23 || $minutes > 59) {
      return NULL;
    }
    return $hours * 60 + $minutes;
  }

}
