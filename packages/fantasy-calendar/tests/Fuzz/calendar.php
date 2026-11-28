<?php

declare(strict_types=1);

/*
 * The fuzz target of the calendars read from arrays, run by `phil-dev fuzz`: any JSON is either refused with the
 * library's own exceptions or gives a calendar that writes itself back to the same array, whose first day of year 1
 * goes to its date and back.
 */

use Tilttek\FantasyCalendar\CalendarCodec;
use Tilttek\FantasyCalendar\Day;
use Tilttek\FantasyCalendar\Exception\FantasyCalendarException;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $data = json_decode($input, true, 8, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($data)) {
        return;
    }

    try {
        $calendar = CalendarCodec::fromArray($data);
    } catch (FantasyCalendarException) {
        // The refusals the codec promises for data it cannot read.
        return;
    }

    $written = CalendarCodec::toArray($calendar);
    if (CalendarCodec::toArray(CalendarCodec::fromArray($written)) !== $written) {
        throw new LogicException('A calendar does not read back to itself.');
    }

    $date = $calendar->date(new Day($calendar->id, 0));
    if (0 !== $calendar->dayOf($date)->number) {
        throw new LogicException('Day 0 does not go to its date and back.');
    }
});
$config->setMaxLen(2048);
