<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

use Tilttek\FantasyCalendar\Exception\InvalidCalendar;
use Tilttek\FantasyCalendar\Exception\OutOfRange;

/**
 * The bounds that keep every computation of the library short and exact, whatever its definition.
 */
final class Limits
{
    /**
     * The farthest year from year 0, before or after it.
     */
    public const int YEAR = 1_000_000;

    /**
     * The most months of a year, and the most days of a month.
     */
    public const int MONTHS = 100;

    public const int MONTH_DAYS = 1000;

    /**
     * The most days of a week.
     */
    public const int WEEKDAYS = 100;

    /**
     * The longest period of the leap years, the least common multiple of their cycles, in years.
     */
    public const int LEAP_PERIOD = 10_000;

    /**
     * The most leap cycles, moons, eras and seasons of a calendar.
     */
    public const int CYCLES = 16;

    public const int MOONS = 16;

    public const int ERAS = 100;

    public const int SEASONS = 16;

    /**
     * The longest cycle of a moon, in days, and the finest denominator of its length.
     */
    public const int MOON_CYCLE = 10_000;

    public const int MOON_PRECISION = 1_000_000;

    /**
     * The longest range of days an agenda lists at once.
     */
    public const int RANGE = 3660;

    /**
     * The most calendars in a registry, and the most links between two of them.
     */
    public const int CALENDARS = 256;

    public const int HOPS = 16;

    private const string NAME = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/';

    /**
     * @throws InvalidCalendar when the value is not 1 to 64 ASCII letters, digits, dots, underscores and hyphens, from
     *                         a letter or a digit
     */
    public static function identifier(string $value, string $what): string
    {
        if (1 !== preg_match(self::NAME, $value)) {
            throw InvalidCalendar::because(\sprintf('%s is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, from a letter or a digit; "%s" is not.', $what, mb_strimwidth($value, 0, 80, '…')));
        }

        return $value;
    }

    /**
     * @throws InvalidCalendar when the label is empty or longer than 200 characters
     */
    public static function label(string $value, string $what): string
    {
        if ('' === trim($value) || mb_strlen($value) > 200 || !mb_check_encoding($value, 'UTF-8')) {
            throw InvalidCalendar::because(\sprintf('%s is a text of 1 to 200 characters.', $what));
        }

        return $value;
    }

    /**
     * @throws InvalidCalendar when the value is out of its range
     */
    public static function between(int $value, int $minimum, int $maximum, string $what): int
    {
        if ($value < $minimum || $value > $maximum) {
            throw InvalidCalendar::because(\sprintf('%s lies from %d to %d; it is %d.', $what, $minimum, $maximum, $value));
        }

        return $value;
    }

    /**
     * @throws OutOfRange when the year is farther than {@see self::YEAR} from year 0
     */
    public static function year(int $year): int
    {
        if ($year < -self::YEAR || $year > self::YEAR) {
            throw OutOfRange::because(\sprintf('A year lies from -%2$d to %2$d; it is %1$d.', $year, self::YEAR));
        }

        return $year;
    }

    /**
     * The floor of a division by a positive divisor, which `intdiv()` rounds towards zero instead.
     */
    public static function floorDiv(int $dividend, int $divisor): int
    {
        $quotient = intdiv($dividend, $divisor);

        return $dividend % $divisor < 0 ? $quotient - 1 : $quotient;
    }

    /**
     * The remainder of a division by a positive divisor, from 0 to the divisor less one.
     */
    public static function mod(int $dividend, int $divisor): int
    {
        return (($dividend % $divisor) + $divisor) % $divisor;
    }
}
