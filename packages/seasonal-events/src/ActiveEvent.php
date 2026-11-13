<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents;

use DateTimeImmutable;

/**
 * An event in one time of its season: when it runs, from `from`, included, to `until`, excluded, and from when it is
 * announced.
 */
final readonly class ActiveEvent
{
    public function __construct(
        public Season $season,
        public SeasonalEvent $event,
        public Period $period,
        public DateTimeImmutable $from,
        public DateTimeImmutable $until,
    ) {
    }

    public function contains(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->from && $moment < $this->until;
    }

    /**
     * The moment it is announced from: its start, less the days of its announcement, in the season's timezone.
     */
    public function announcedFrom(): DateTimeImmutable
    {
        $timezone = $this->season->schedule->timezone;

        return Days::start(Days::of($this->from, $timezone) - $this->event->announceDays, $timezone);
    }

    /**
     * Whether it is announced and has not started yet at a moment.
     */
    public function isAnnouncedAt(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->announcedFrom() && $moment < $this->from;
    }

    /**
     * A key unique to this event in this time of its season, `winter:snow-week:2026-12-15`.
     */
    public function key(): string
    {
        return \sprintf('%s:%s:%s', $this->season->id, $this->event->id, $this->period->code);
    }

    /**
     * @return array{season: string, event: string, period: string, from: string, until: string, contents: list<array{kind: string, reference: string}>, multiplier: ?array{ledger: string, percent: int}}
     */
    public function toArray(): array
    {
        return [
            'season' => $this->season->id,
            'event' => $this->event->id,
            'period' => $this->period->code,
            'from' => $this->from->format(\DATE_ATOM),
            'until' => $this->until->format(\DATE_ATOM),
            'contents' => array_map(static fn (Content $content): array => $content->toArray(), $this->event->contents),
            'multiplier' => $this->event->multiplier?->toArray(),
        ];
    }
}
