<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

/**
 * The season of a day: which one, the climate zone of the calendar, how far into the season the day is and how long
 * the season lasts, in days.
 */
final readonly class SeasonAt
{
    /**
     * @param int $day    the day of the season, from 1
     * @param int $length the days of the season
     */
    public function __construct(public Season $season, public ?string $climateZone, public int $day, public int $length)
    {
    }
}
