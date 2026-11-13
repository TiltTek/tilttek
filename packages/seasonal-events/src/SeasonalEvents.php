<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\SeasonalEvents\Contract\ActiveContent;
use Tilttek\SeasonalEvents\Contract\SeasonCatalogue;

/**
 * The seasons of the site at a moment: which are on, which events run, what they bring, and which events are announced.
 * It reads the catalogue at each call and keeps no state between two.
 */
final readonly class SeasonalEvents implements ActiveContent
{
    public const int MAX_UPCOMING = 50;

    public function __construct(
        private SeasonCatalogue $seasons,
        private ClockInterface $clock,
    ) {
    }

    /**
     * What is on at a moment, now by default.
     */
    public function at(?DateTimeImmutable $moment = null): Snapshot
    {
        $moment ??= $this->clock->now();
        $seasons = [];
        $events = [];

        foreach ($this->seasons->all() as $season) {
            $period = $season->periodAt($moment);

            if (!$period instanceof Period) {
                continue;
            }

            $seasons[] = new ActiveSeason($season, $period);
            $events = [...$events, ...$season->eventsOn($period, Days::of($moment, $season->schedule->timezone))[0]];
        }

        return new Snapshot($moment, $seasons, $events);
    }

    /**
     * The events announced at a moment that have not started yet, the soonest first: those of the seasons on, and those
     * of the next time of each season.
     *
     * @return list<ActiveEvent>
     */
    public function announced(?DateTimeImmutable $moment = null, int $limit = 10): array
    {
        $moment ??= $this->clock->now();
        $announced = [];

        foreach ($this->seasons->all() as $season) {
            $day = Days::of($moment, $season->schedule->timezone);

            foreach (array_filter([$season->periodAt($moment), $season->schedule->nextPeriod($moment)]) as $period) {
                $announced = [...$announced, ...$season->eventsOn($period, $day)[1]];
            }
        }

        usort($announced, static fn (ActiveEvent $one, ActiveEvent $other): int => [$one->from, $one->key()] <=> [$other->from, $other->key()]);

        return \array_slice($announced, 0, max(0, min($limit, self::MAX_UPCOMING)));
    }

    /**
     * The next time of a season after a moment, or null when the season is unknown or never comes again.
     */
    public function next(string $season, ?DateTimeImmutable $after = null): ?Period
    {
        return $this->seasons->find($season)?->schedule->nextPeriod($after ?? $this->clock->now());
    }

    #[Override]
    public function active(ContentKind $kind, ?DateTimeImmutable $moment = null): array
    {
        return $this->at($moment)->contents($kind);
    }

    #[Override]
    public function isActive(ContentKind $kind, ResourceReference $reference, ?DateTimeImmutable $moment = null): bool
    {
        return array_any($this->active($kind, $moment), static fn (ResourceReference $active): bool => $active->equals($reference));
    }

    #[Override]
    public function multiplier(string $ledger, ?DateTimeImmutable $moment = null): ?Multiplier
    {
        return $this->at($moment)->multiplier($ledger);
    }
}
