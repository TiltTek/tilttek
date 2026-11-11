<?php

declare(strict_types=1);

namespace Tilttek\Points\Rule;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The period a cap counts over: a calendar day, an ISO week from Monday, a calendar month or year, in the timezone of
 * the rules — or ever, for a gain a member makes once.
 */
enum Period: string
{
    case Day = 'day';

    case Week = 'week';

    case Month = 'month';

    case Year = 'year';

    case Ever = 'ever';

    /**
     * The start, included, and the end, excluded, of the period holding the moment; both null for `Ever`.
     *
     * @return array{?DateTimeImmutable, ?DateTimeImmutable}
     */
    public function bounds(DateTimeImmutable $moment, DateTimeZone $timezone): array
    {
        $local = $moment->setTimezone($timezone);
        $day = $local->setTime(0, 0);

        return match ($this) {
            self::Day => [$day, $day->modify('+1 day')],
            self::Week => [$start = $day->setISODate((int) $local->format('o'), (int) $local->format('W')), $start->modify('+1 week')],
            self::Month => [$start = $day->setDate((int) $local->format('Y'), (int) $local->format('n'), 1), $start->modify('+1 month')],
            self::Year => [$start = $day->setDate((int) $local->format('Y'), 1, 1), $start->modify('+1 year')],
            self::Ever => [null, null],
        };
    }
}
