<?php

declare(strict_types=1);

namespace Tilttek\Raffle;

use DateTimeImmutable;
use DateTimeZone;
use Tilttek\Raffle\Exception\InvalidRaffle;

/**
 * The one way the library writes a time, in UTC to the microsecond, `2026-11-12T20:15:03.250000Z`, so that a proof
 * reads the same on every server.
 *
 * @internal
 */
final class Instant
{
    private const string FORMAT = 'Y-m-d\TH:i:s.u\Z';

    private function __construct()
    {
    }

    public static function write(DateTimeImmutable $at): string
    {
        return $at->setTimezone(new DateTimeZone('UTC'))->format(self::FORMAT);
    }

    /**
     * @throws InvalidRaffle when the text is not a time as `write()` writes it
     */
    public static function read(string $text): DateTimeImmutable
    {
        $at = DateTimeImmutable::createFromFormat(self::FORMAT, $text, new DateTimeZone('UTC'));

        if (!$at instanceof DateTimeImmutable || $at->format(self::FORMAT) !== $text) {
            throw InvalidRaffle::because('A time is written in UTC to the microsecond, as 2026-11-12T20:15:03.250000Z.');
        }

        return $at;
    }

    /**
     * The time in UTC, to the microsecond, as it reads back.
     */
    public static function utc(DateTimeImmutable $at): DateTimeImmutable
    {
        return self::read(self::write($at));
    }
}
