<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

use Tilttek\FantasyCalendar\Exception\CalendarMismatch;
use Tilttek\FantasyCalendar\Exception\InvalidCalendar;
use Tilttek\FantasyCalendar\Exception\OutOfRange;

/**
 * The span of an entry of the world: the birth and death of a character, the founding and fall of a city. Its age on a
 * day is computed by the calendar, and stops at the end.
 */
final readonly class Lifespan
{
    /**
     * @throws CalendarMismatch when the two days belong to different calendars
     * @throws InvalidCalendar  when the end comes before the start
     */
    public function __construct(public Day $start, public ?Day $end = null)
    {
        if ($end instanceof Day && $end->isBefore($start)) {
            throw InvalidCalendar::because('A lifespan ends on or after the day it starts.');
        }
    }

    /**
     * The age in whole years on a day; null before the start; the age at the end on any day after it.
     *
     * @throws CalendarMismatch when the day belongs to another calendar
     * @throws OutOfRange       when a day is out of the bounds of the years
     */
    public function ageOn(Calendar $calendar, Day $day): ?int
    {
        if ($day->isBefore($this->start)) {
            return null;
        }

        $last = $this->end instanceof Day && $day->isAfter($this->end) ? $this->end : $day;

        return $calendar->yearsBetween($this->start, $last);
    }

    /**
     * Whether the span has ended by a day.
     *
     * @throws CalendarMismatch when the day belongs to another calendar
     */
    public function hasEndedBy(Day $day): bool
    {
        return $this->end instanceof Day && $day->isAfter($this->end);
    }
}
