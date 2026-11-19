<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Application;

use Tilttek\AdventCalendar\Calendar;

/**
 * A calendar as the screens show it: the calendar of the library and its title.
 */
final readonly class CalendarEntry
{
    public function __construct(
        public Calendar $calendar,
        public string $title,
    ) {
    }
}
