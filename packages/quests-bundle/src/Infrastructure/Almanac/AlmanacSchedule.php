<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Infrastructure\Almanac;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\Almanac\Almanac;
use Phil\Almanac\ComputedDate;
use Phil\Almanac\Definition;
use Phil\Almanac\Exception\OutOfRange;
use Tilttek\Quests\Schedule\Occurrence;
use Tilttek\Quests\Schedule\Schedule;

/**
 * A quest that comes back at dates `phil/almanac` computes — a week from Easter, the days around the winter solstice,
 * the second weekend of October —, whole days in a timezone. Each occurrence is named by the date it opens on, such
 * as `2027-03-26`, and closes at the end of its last day.
 */
final readonly class AlmanacSchedule implements Schedule
{
    private Almanac $almanac;

    public function __construct(Definition $definition, DateTimeZone $timezone)
    {
        $this->almanac = new Almanac([$definition], $timezone);
    }

    #[Override]
    public function occurrenceAt(DateTimeImmutable $moment): ?Occurrence
    {
        try {
            $date = $this->almanac->between($moment, $moment->modify('+1 second'))[0] ?? null;
        } catch (OutOfRange) {
            $date = null;
        }

        if (!$date instanceof ComputedDate) {
            return null;
        }

        return new Occurrence($date->span->start->format('Y-m-d'), $date->span->start, $date->span->end->modify('+1 day'));
    }
}
