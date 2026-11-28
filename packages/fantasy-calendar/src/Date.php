<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

/**
 * A day written as its year, month and day of the month, as a calendar reads it: the month and the day count from 1,
 * the year may be 0 or negative. A {@see Calendar} turns a date into a {@see Day} and back.
 */
final readonly class Date
{
    public function __construct(public int $year, public int $month, public int $day)
    {
    }

    /**
     * -1, 0 or 1 as this date comes before, on or after the other, by year, then month, then day.
     */
    public function compare(self $other): int
    {
        return [$this->year, $this->month, $this->day] <=> [$other->year, $other->month, $other->day];
    }

    /**
     * -1, 0 or 1 as this date comes before, on or after the other within a year, by month, then day.
     */
    public function compareWithinYear(self $other): int
    {
        return [$this->month, $this->day] <=> [$other->month, $other->day];
    }
}
