<?php

declare(strict_types=1);

namespace Tilttek\Collections\Daily;

use DateTimeImmutable;
use DateTimeZone;
use Tilttek\Collections\Exception\InvalidCollection;

/**
 * The days of the calendar, written `Y-m-d`.
 */
final class Days
{
    private function __construct()
    {
    }

    public static function isDay(string $day): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $day);

        return false !== $date && $date->format('Y-m-d') === $day;
    }

    /**
     * The day at midnight in a timezone.
     *
     * @throws InvalidCollection when it is not a day
     */
    public static function midnight(string $day, DateTimeZone $timezone): DateTimeImmutable
    {
        if (!self::isDay($day)) {
            throw InvalidCollection::because(\sprintf('"%s" is not a day written Y-m-d.', $day));
        }

        return new DateTimeImmutable($day, $timezone);
    }
}
