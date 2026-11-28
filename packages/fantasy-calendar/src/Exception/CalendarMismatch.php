<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar\Exception;

use LogicException;

/**
 * Two days of different calendars compared or combined without converting one into the other first.
 */
final class CalendarMismatch extends LogicException implements FantasyCalendarException
{
    public static function between(string $first, string $second): self
    {
        return new self(\sprintf('A day of the calendar "%s" is not a day of the calendar "%s": convert it first.', $first, $second));
    }
}
