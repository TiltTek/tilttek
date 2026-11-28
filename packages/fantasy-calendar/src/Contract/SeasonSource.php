<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar\Contract;

use Tilttek\FantasyCalendar\Day;
use Tilttek\FantasyCalendar\Exception\CalendarMismatch;
use Tilttek\FantasyCalendar\SeasonAt;

/**
 * The season and the climate zone of a day, for what draws the weather of the game world. {@see \Tilttek\FantasyCalendar\Calendar}
 * gives them from its own seasons; an application may give them otherwise — by region, by plane.
 */
interface SeasonSource
{
    /**
     * The season of the day, or null when the source has no season for it.
     *
     * @throws CalendarMismatch when the day belongs to another calendar
     */
    public function seasonOn(Day $day): ?SeasonAt;
}
