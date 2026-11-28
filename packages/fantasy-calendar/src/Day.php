<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

use Tilttek\FantasyCalendar\Exception\CalendarMismatch;

/**
 * A day of a calendar, as a whole number of days from the first day of its year 1 — day 0 —, negative before it. Two
 * days are compared by their methods, never with `==`, and only within one calendar.
 */
final readonly class Day
{
    public function __construct(public string $calendar, public int $number)
    {
    }

    /**
     * -1, 0 or 1 as this day comes before, on or after the other.
     *
     * @throws CalendarMismatch when the days belong to different calendars
     */
    public function compare(self $other): int
    {
        $this->sameCalendar($other);

        return $this->number <=> $other->number;
    }

    /**
     * @throws CalendarMismatch when the days belong to different calendars
     */
    public function isBefore(self $other): bool
    {
        return $this->compare($other) < 0;
    }

    /**
     * @throws CalendarMismatch when the days belong to different calendars
     */
    public function isAfter(self $other): bool
    {
        return $this->compare($other) > 0;
    }

    /**
     * @throws CalendarMismatch when the days belong to different calendars
     */
    public function isSameDay(self $other): bool
    {
        return 0 === $this->compare($other);
    }

    public function plus(int $days): self
    {
        return new self($this->calendar, $this->number + $days);
    }

    /**
     * The number of days from the other day to this one, negative when the other comes after.
     *
     * @throws CalendarMismatch when the days belong to different calendars
     */
    public function daysSince(self $other): int
    {
        $this->sameCalendar($other);

        return $this->number - $other->number;
    }

    /**
     * @throws CalendarMismatch when the days belong to different calendars
     */
    private function sameCalendar(self $other): void
    {
        if ($this->calendar !== $other->calendar) {
            throw CalendarMismatch::between($this->calendar, $other->calendar);
        }
    }
}
