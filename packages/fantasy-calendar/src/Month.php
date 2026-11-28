<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

use Tilttek\FantasyCalendar\Exception\InvalidCalendar;

/**
 * A month of a calendar: its days, the days it gains in a leap year, and whether its days count in the week — a
 * festival between two months often stands outside it.
 */
final readonly class Month
{
    /**
     * @throws InvalidCalendar when a value is out of its bounds
     */
    public function __construct(public string $id, public string $name, public int $days, public int $leapDays = 0, public bool $inWeek = true)
    {
        Limits::identifier($id, 'A month identifier');
        Limits::label($name, 'A month name');
        Limits::between($days, 0, Limits::MONTH_DAYS, 'The days of a month');
        Limits::between($leapDays, 0, Limits::MONTH_DAYS - $days, 'The leap days of a month');
        if (0 === $days + $leapDays) {
            throw InvalidCalendar::because(\sprintf('The month "%s" has days in a common year or in a leap year.', $id));
        }
    }

    public function length(bool $leap): int
    {
        return $leap ? $this->days + $this->leapDays : $this->days;
    }
}
