<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Internal;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Instants after the epoch as milliseconds since it: the clock counts and compares them as integers, never as dates.
 *
 * @internal
 */
final class Millis
{
    private function __construct()
    {
    }

    public static function of(DateTimeInterface $moment): int
    {
        return (int) $moment->format('Uv');
    }

    public static function at(int $millis): DateTimeImmutable
    {
        return new DateTimeImmutable(\sprintf('@%d.%03d', intdiv($millis, 1000), $millis % 1000))->setTimezone(new DateTimeZone('UTC'));
    }
}
