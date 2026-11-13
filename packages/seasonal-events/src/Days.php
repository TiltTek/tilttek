<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents;

use DateTimeImmutable;
use DateTimeZone;
use Tilttek\SeasonalEvents\Exception\InvalidSeason;

/**
 * The calendar arithmetic the library shares: a date written `Y-m-d` as a number of days since 1970-01-01, and back,
 * and the first moment of a day in a timezone, across changes of time.
 *
 * @internal
 */
final class Days
{
    /** The number of 8999-12-31, the last day a date is read for. */
    public const int LAST_DAY = 2_567_654;

    private const int SECONDS_PER_DAY = 86_400;

    private function __construct()
    {
    }

    /**
     * The days from 1970-01-01 to a date written `Y-m-d`, in the years 1000 to 8999.
     */
    public static function number(string $date): int
    {
        if (1 !== preg_match('/^([1-8]\d{3})-(\d{2})-(\d{2})$/D', $date, $parts)) {
            throw self::refused($date);
        }

        [$year, $month, $day] = array_map(intval(...), \array_slice($parts, 1));

        if (!checkdate($month, $day, $year)) {
            throw self::refused($date);
        }

        return self::fromCivil($year, $month, $day);
    }

    /**
     * The days from 1970-01-01 to a valid day of the proleptic Gregorian calendar, by eras of 400 years.
     */
    public static function fromCivil(int $year, int $month, int $day): int
    {
        $year -= $month <= 2 ? 1 : 0;
        $era = intdiv($year >= 0 ? $year : $year - 399, 400);
        $yearOfEra = $year - $era * 400;
        $dayOfYear = intdiv(153 * ($month + ($month > 2 ? -3 : 9)) + 2, 5) + $day - 1;
        $dayOfEra = $yearOfEra * 365 + intdiv($yearOfEra, 4) - intdiv($yearOfEra, 100) + $dayOfYear;

        return $era * 146_097 + $dayOfEra - 719_468;
    }

    public static function date(int $number): string
    {
        return gmdate('Y-m-d', $number * self::SECONDS_PER_DAY);
    }

    /**
     * The year of a day, by its number.
     */
    public static function year(int $number): int
    {
        return (int) gmdate('Y', $number * self::SECONDS_PER_DAY);
    }

    /**
     * The number of the day a moment falls on, in a timezone.
     */
    public static function of(DateTimeImmutable $moment, DateTimeZone $timezone): int
    {
        return self::number($moment->setTimezone($timezone)->format('Y-m-d'));
    }

    /**
     * The first moment of a day, by its number, in a timezone: midnight, or the first moment after it on a date whose
     * midnight a change of time skips.
     */
    public static function start(int $number, DateTimeZone $timezone): DateTimeImmutable
    {
        return new DateTimeImmutable(self::date($number), $timezone);
    }

    /**
     * The calendar days from the day of one moment to the day of another, in the timezone of the first.
     */
    public static function between(DateTimeImmutable $from, DateTimeImmutable $until): int
    {
        return self::of($until, $from->getTimezone()) - self::of($from, $from->getTimezone());
    }

    private static function refused(string $date): InvalidSeason
    {
        return InvalidSeason::because(\sprintf('"%s" is not a date of the years 1000 to 8999 written YYYY-MM-DD.', $date));
    }
}
