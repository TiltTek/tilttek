<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar\Exception;

use RangeException;

/**
 * A computation that would leave the bounds of the library: a year too far from the first one, a range of days too
 * long, too many calendars between two linked ones.
 */
final class OutOfRange extends RangeException implements FantasyCalendarException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
