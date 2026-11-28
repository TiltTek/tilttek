<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar\Exception;

use OutOfBoundsException;

/**
 * A calendar the registry does not hold, or two calendars no chain of links joins.
 */
final class UnknownCalendar extends OutOfBoundsException implements FantasyCalendarException
{
    public static function named(string $id): self
    {
        return new self(\sprintf('No calendar "%s" is registered.', $id));
    }

    public static function unlinked(string $from, string $to): self
    {
        return new self(\sprintf('No chain of links joins the calendar "%s" to the calendar "%s".', $from, $to));
    }
}
