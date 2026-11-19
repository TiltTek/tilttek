<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tilttek\AdventCalendarBundle\Infrastructure\Http\CalendarController;

/*
 * The routes of the calendars' screen and of the opening of a box:
 *
 *     # config/routes/tilttek_advent_calendar.yaml
 *     tilttek_advent_calendar:
 *         resource: '@TilttekAdventCalendarBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $calendar = ['calendar' => CalendarController::CALENDAR];

    $routes->add(CalendarController::SCREEN, '/advent-calendar/{calendar}')
        ->controller([CalendarController::class, 'screen'])->methods(['GET'])->requirements($calendar);
    $routes->add(CalendarController::OPEN, '/advent-calendar/{calendar}/boxes/{day}')
        ->controller([CalendarController::class, 'open'])->methods(['POST'])->requirements([...$calendar, 'day' => '[1-9][0-9]?']);
};
