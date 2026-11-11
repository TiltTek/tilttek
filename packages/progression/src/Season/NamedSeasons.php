<?php

declare(strict_types=1);

namespace Tilttek\Progression\Season;

use DateTimeImmutable;
use Override;
use Tilttek\Progression\Exception\InvalidProgression;

/**
 * Seasons the application names and dates — a summer, an event —, which never overlap and may leave time between
 * them: an activity between two seasons counts for no season.
 */
final readonly class NamedSeasons implements SeasonSchedule
{
    /**
     * @var array<string, Season> by code, sorted by start
     */
    public array $seasons;

    public function __construct(Season ...$seasons)
    {
        usort($seasons, static fn (Season $a, Season $b): int => $a->start <=> $b->start);
        $byCode = [];
        $previous = null;

        foreach ($seasons as $season) {
            if (isset($byCode[$season->code])) {
                throw InvalidProgression::because(\sprintf('Two seasons are coded "%s".', $season->code));
            }

            if ($previous instanceof Season && $season->start < $previous->end) {
                throw InvalidProgression::because(\sprintf('The seasons "%s" and "%s" overlap.', $previous->code, $season->code));
            }

            $byCode[$season->code] = $previous = $season;
        }

        $this->seasons = $byCode;
    }

    #[Override]
    public function at(DateTimeImmutable $moment): ?Season
    {
        return array_find($this->seasons, static fn (Season $season): bool => $season->holds($moment));
    }

    #[Override]
    public function named(string $code): ?Season
    {
        return $this->seasons[$code] ?? null;
    }
}
