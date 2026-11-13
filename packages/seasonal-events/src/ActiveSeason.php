<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents;

/**
 * A season in the time it is on.
 */
final readonly class ActiveSeason
{
    public function __construct(
        public Season $season,
        public Period $period,
    ) {
    }

    /**
     * @return array{season: string, period: array{code: string, from: string, until: string}, contents: list<array{kind: string, reference: string}>}
     */
    public function toArray(): array
    {
        return [
            'season' => $this->season->id,
            'period' => $this->period->toArray(),
            'contents' => array_map(static fn (Content $content): array => $content->toArray(), $this->season->contents),
        ];
    }
}
