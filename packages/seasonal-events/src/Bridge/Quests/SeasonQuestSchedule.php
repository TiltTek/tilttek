<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents\Bridge\Quests;

use DateTimeImmutable;
use Override;
use Tilttek\Quests\Schedule\Occurrence;
use Tilttek\Quests\Schedule\Schedule;
use Tilttek\SeasonalEvents\Period;
use Tilttek\SeasonalEvents\Season;
use Tilttek\SeasonalEvents\SeasonalEvent;

/**
 * The schedule of a `tilttek/quests` quest that opens with a season, or with one of its events: each time the season
 * comes is an occurrence, whose code is the date of the season's first day, so that the quest is accomplished once per
 * year. Used only when `tilttek/quests` is installed.
 */
final readonly class SeasonQuestSchedule implements Schedule
{
    public function __construct(
        private Season $season,
        private ?SeasonalEvent $event = null,
    ) {
    }

    #[Override]
    public function occurrenceAt(DateTimeImmutable $moment): ?Occurrence
    {
        $period = $this->season->periodAt($moment);

        if (!$period instanceof Period) {
            return null;
        }

        if (!$this->event instanceof SeasonalEvent) {
            return new Occurrence($period->code, $period->from, $period->until);
        }

        $active = $this->season->eventIn($this->event, $period);

        return $active->contains($moment) ? new Occurrence($period->code, $active->from, $active->until) : null;
    }
}
