<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Clock\MockClock;
use Tilttek\SeasonalEvents\Content;
use Tilttek\SeasonalEvents\Multiplier;
use Tilttek\SeasonalEvents\Season;
use Tilttek\SeasonalEvents\SeasonalEvent;
use Tilttek\SeasonalEvents\SeasonalEvents;
use Tilttek\SeasonalEvents\SeasonSchedule;
use Tilttek\SeasonalEvents\Store\InMemorySeasonCatalogue;

/**
 * What a page costs that asks what is on — every page of a site that decorates itself by season —: the snapshot of
 * 12 yearly seasons of 5 events each, read years after their first time, and the events announced.
 */
final readonly class SnapshotBench
{
    private SeasonalEvents $events;

    private DateTimeImmutable $moment;

    public function __construct()
    {
        $seasons = [];

        for ($month = 1; $month <= 12; ++$month) {
            $events = [];

            for ($index = 1; $index <= 5; ++$index) {
                $events[] = new SeasonalEvent('event-'.$index, $index * 4, 7, [Content::item('item:m'.$month.'e'.$index)], new Multiplier('points', 150), 3);
            }

            $seasons[] = new Season('season-'.$month, SeasonSchedule::yearly('America/Toronto', \sprintf('2020-%02d-01', $month), 28), [Content::quest('quest:m'.$month), Content::decoration('decoration:m'.$month)], $events);
        }

        $this->moment = new DateTimeImmutable('2030-12-20 12:00', new DateTimeZone('America/Toronto'));
        $this->events = new SeasonalEvents(new InMemorySeasonCatalogue($seasons), new MockClock($this->moment));
    }

    /**
     * @Revs(50)
     *
     * @Iterations(3)
     */
    public function benchSnapshot(): int
    {
        return \count($this->events->at()->items());
    }

    /**
     * @Revs(50)
     *
     * @Iterations(3)
     */
    public function benchAnnounced(): int
    {
        return \count($this->events->announced());
    }
}
