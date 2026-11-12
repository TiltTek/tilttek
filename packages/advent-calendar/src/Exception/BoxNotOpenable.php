<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar\Exception;

use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;

/**
 * A box a member may not open now: its day has not come, or its opening window has passed.
 */
final class BoxNotOpenable extends RuntimeException implements AdventCalendarException
{
    public static function notYet(string $calendar, int $day, DateTimeImmutable $opensAt): self
    {
        return new self(\sprintf('The box %d of the calendar "%s" opens at %s.', $day, $calendar, $opensAt->format(DateTimeInterface::ATOM)));
    }

    public static function missed(string $calendar, int $day, DateTimeImmutable $closedAt): self
    {
        return new self(\sprintf('The box %d of the calendar "%s" could be opened until %s.', $day, $calendar, $closedAt->format(DateTimeInterface::ATOM)));
    }
}
