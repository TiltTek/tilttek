<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar\Template;

use Tilttek\FantasyCalendar\Calendar;
use Tilttek\FantasyCalendar\CalendarCodec;
use Tilttek\FantasyCalendar\Exception\InvalidCalendar;

/**
 * The proleptic Gregorian calendar as a model to copy: twelve months, a seven-day week from Monday, the leap years
 * of every 4 years but not every 100 but every 400, one moon, the eras before and of the common era — year 0 is
 * 1 BCE —, and the four seasons of the northern hemisphere. Its day 0, the 1st of January of year 1, is a Monday.
 */
final class Gregorian
{
    /**
     * The definition, as {@see CalendarCodec::fromArray()} reads it: copy it, rename it, change what the world needs.
     *
     * @return array<string, mixed>
     */
    public static function definition(string $id = 'gregorian'): array
    {
        $month = static fn (string $id, string $name, int $days, int $leapDays = 0): array => ['id' => $id, 'name' => $name, 'days' => $days, 'leapDays' => $leapDays, 'inWeek' => true];

        return [
            'id' => $id,
            'name' => 'Gregorian',
            'months' => [
                $month('january', 'January', 31),
                $month('february', 'February', 28, 1),
                $month('march', 'March', 31),
                $month('april', 'April', 30),
                $month('may', 'May', 31),
                $month('june', 'June', 30),
                $month('july', 'July', 31),
                $month('august', 'August', 31),
                $month('september', 'September', 30),
                $month('october', 'October', 31),
                $month('november', 'November', 30),
                $month('december', 'December', 31),
            ],
            'weekdays' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'firstWeekday' => 0,
            'leap' => [
                ['every' => 4, 'offset' => 0, 'leap' => true],
                ['every' => 100, 'offset' => 0, 'leap' => false],
                ['every' => 400, 'offset' => 0, 'leap' => true],
            ],
            // The mean synodic month, and the new moon of the 6th of January 2000.
            'moons' => [['id' => 'moon', 'name' => 'Moon', 'cycle' => '29.530588', 'newMoon' => 730_124]],
            'eras' => [
                ['id' => 'bce', 'name' => 'Before the Common Era', 'start' => null],
                ['id' => 'ce', 'name' => 'Common Era', 'start' => 1],
            ],
            'seasons' => [
                ['id' => 'spring', 'name' => 'Spring', 'month' => 'march', 'day' => 20],
                ['id' => 'summer', 'name' => 'Summer', 'month' => 'june', 'day' => 21],
                ['id' => 'autumn', 'name' => 'Autumn', 'month' => 'september', 'day' => 22],
                ['id' => 'winter', 'name' => 'Winter', 'month' => 'december', 'day' => 21],
            ],
            'climateZone' => null,
        ];
    }

    /**
     * @throws InvalidCalendar when the identifier is not a valid one
     */
    public static function calendar(string $id = 'gregorian'): Calendar
    {
        return CalendarCodec::fromArray(self::definition($id));
    }
}
