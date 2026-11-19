<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Application;

use DateTimeImmutable;
use Tilttek\AdventCalendar\BoxState;
use Tilttek\AdventCalendar\BoxView;
use Tilttek\AdventCalendar\Calendar;

/**
 * The boxes as a visitor sees them, who has opened none: locked before their day, openable in their window, missed
 * after it. No surprise is ever shown.
 */
final class VisitorBoxes
{
    private function __construct()
    {
    }

    /**
     * @return list<BoxView>
     */
    public static function of(Calendar $calendar, DateTimeImmutable $now): array
    {
        $boxes = [];

        for ($day = 1; $day <= $calendar->days(); ++$day) {
            $window = $calendar->window($day);

            $state = match (true) {
                !$window->hasStarted($now) => BoxState::Locked,
                $window->hasEnded($now) => BoxState::Missed,
                default => BoxState::Openable,
            };

            $boxes[] = new BoxView($day, $calendar->dateOf($day), $state, $window);
        }

        return $boxes;
    }
}
