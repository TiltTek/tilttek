<?php

declare(strict_types=1);

namespace Tilttek\Progression\Streak;

use DateTimeImmutable;
use DateTimeZone;

/**
 * What a streak counts: days, or ISO weeks from Monday. Each day or week has a number — days since 1 January 1970,
 * weeks since the Monday before it — read in the timezone of the member, so that midnight is theirs.
 */
enum StreakUnit: string
{
    case Day = 'day';

    case Week = 'week';

    /**
     * The number of the day or week holding the moment, in the timezone.
     */
    public function index(DateTimeImmutable $moment, DateTimeZone $timezone): int
    {
        $local = $moment->setTimezone($timezone);

        if (self::Week === $this) {
            $local = $local->modify('monday this week');
        }

        $day = intdiv(new DateTimeImmutable($local->format('Y-m-d'), new DateTimeZone('UTC'))->getTimestamp(), 86400);

        return self::Day === $this ? $day : intdiv($day + 3, 7);
    }

    /**
     * The day (`2026-11-07`) or the week (`2026-W45`, read on its Thursday) a number stands for.
     */
    public function label(int $index): string
    {
        return self::Day === $this ? gmdate('Y-m-d', $index * 86400) : gmdate('o-\WW', 7 * $index * 86400);
    }
}
