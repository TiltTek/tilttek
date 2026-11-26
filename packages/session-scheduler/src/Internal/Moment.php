<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Internal;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Instants compared and written by their seconds since the epoch, never with `==`: two moments are the same when they
 * name the same instant, whatever their time zones.
 *
 * @internal
 */
final class Moment
{
    private function __construct()
    {
    }

    public static function same(DateTimeInterface $one, DateTimeInterface $other): bool
    {
        return $one->format('U.u') === $other->format('U.u');
    }

    /**
     * The instant written in UTC, as an identifier keeps it: `20270312T233000Z`.
     */
    public static function stamp(DateTimeInterface $moment): string
    {
        return DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }
}
