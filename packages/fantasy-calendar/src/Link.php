<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

use Tilttek\FantasyCalendar\Exception\InvalidCalendar;

/**
 * A day of the campaign two calendars share — the coronation is the 1st of Highsun here and the 14th of the Ninth
 * Moon there —, which converts every day of one into the other, a day lasting as long in both.
 */
final readonly class Link
{
    /**
     * @throws InvalidCalendar when both days belong to the same calendar
     */
    public function __construct(public Day $first, public Day $second)
    {
        if ($first->calendar === $second->calendar) {
            throw InvalidCalendar::because('A link joins two different calendars.');
        }
    }

    /**
     * The days added to a day of the first calendar to get the same day in the second.
     */
    public function shift(): int
    {
        return $this->second->number - $this->first->number;
    }
}
