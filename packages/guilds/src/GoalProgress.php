<?php

declare(strict_types=1);

namespace Tilttek\Guilds;

use DateTimeImmutable;

/**
 * Where a goal stands: the points gathered, the target, how many members brought some — erased members counted as
 * one together —, and whether it is reached. It ranks nobody.
 */
final readonly class GoalProgress
{
    public function __construct(
        public Goal $goal,
        public int $points,
        public int $contributors,
    ) {
    }

    public function isReached(): bool
    {
        return $this->goal->reachedAt instanceof DateTimeImmutable;
    }

    /**
     * The share of the target gathered, 0 to 100, rounded down, never above 100.
     */
    public function percent(): int
    {
        return min(100, intdiv($this->points * 100, $this->goal->target));
    }

    /**
     * The points still missing, 0 once the target is met.
     */
    public function remaining(): int
    {
        return max(0, $this->goal->target - $this->points);
    }
}
