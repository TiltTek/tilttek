<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar\Exception;

use InvalidArgumentException;

/**
 * A calendar, a date or a value the library refuses: a month without days, a date that does not exist, a definition
 * that does not read.
 */
final class InvalidCalendar extends InvalidArgumentException implements FantasyCalendarException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    /**
     * A refusal of a definition read from an array, with the place of the value refused.
     */
    public static function at(string $place, string $reason): self
    {
        return new self(\sprintf('%s: %s.', $place, $reason));
    }
}
