<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

/**
 * A season of a calendar and the day of the year it starts on; it lasts until the next season starts.
 */
final readonly class Season
{
    /**
     * @param int $month the number of the month it starts in, from 1
     * @param int $day   the day of that month it starts on, from 1
     */
    public function __construct(public string $id, public string $name, public int $month, public int $day)
    {
        Limits::identifier($id, 'A season identifier');
        Limits::label($name, 'A season name');
    }
}
