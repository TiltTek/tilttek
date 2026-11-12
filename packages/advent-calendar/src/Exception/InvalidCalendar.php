<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a calendar, a date, a timezone, a policy or a surprise that does not hold together, or
 * a day the calendar has no box for.
 */
final class InvalidCalendar extends InvalidArgumentException implements AdventCalendarException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
