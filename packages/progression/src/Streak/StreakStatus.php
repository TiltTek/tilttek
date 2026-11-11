<?php

declare(strict_types=1);

namespace Tilttek\Progression\Streak;

/**
 * Where a member stands with a streak: the current run — 0 when it is broken —, the best run ever, the day or week the
 * current run started, the last one the member was active, and how many missed units the grace still forgives now.
 * Days and weeks are the numbers of `StreakUnit`.
 */
final readonly class StreakStatus
{
    public function __construct(
        public string $streak,
        public int $current = 0,
        public int $best = 0,
        public ?int $start = null,
        public ?int $lastActive = null,
        public int $graceLeft = 0,
    ) {
    }

    public function isAlive(): bool
    {
        return 0 < $this->current;
    }
}
