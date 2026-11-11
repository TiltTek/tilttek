<?php

declare(strict_types=1);

namespace Tilttek\Badges\Criterion;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Tilttek\Activity\Activity;
use Tilttek\Badges\Exception\InvalidBadge;
use Tilttek\Badges\Progress;

/**
 * Days in a row: *have come 7 days running*. A day counts when the member made at least one of the activities on it,
 * in the timezone of the catalog; the progress is the longest run of days.
 */
final readonly class Streak implements Leaf
{
    public const int MAX_DAYS = 3660;

    public function __construct(
        public Scope $scope,
        public int $days,
        public DateTimeZone $timezone = new DateTimeZone('UTC'),
    ) {
        if ($days < 2 || $days > self::MAX_DAYS) {
            throw InvalidBadge::because(\sprintf('A streak is 2 to %d days; this one is %d.', self::MAX_DAYS, $days));
        }
    }

    #[Override]
    public function accepts(Activity $activity): bool
    {
        return $this->scope->accepts($activity);
    }

    #[Override]
    public function patterns(): array
    {
        return $this->scope->patterns;
    }

    #[Override]
    public function leaves(string $path): array
    {
        return [$path => $this];
    }

    #[Override]
    public function progress(string $path, Reading $reading): Progress
    {
        $days = array_map(fn (DateTimeImmutable $moment): string => $moment->setTimezone($this->timezone)->format('Y-m-d'), $reading->moments($path));
        sort($days);

        // The run ending on each day, from the run ending the day before; a day seen twice is counted again the same.
        $runs = [];

        foreach ($days as $day) {
            $runs[$day] = ($runs[new DateTimeImmutable($day)->modify('-1 day')->format('Y-m-d')] ?? 0) + 1;
        }

        return new Progress(max([0, ...$runs]), $this->days);
    }
}
