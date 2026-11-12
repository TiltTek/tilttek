<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals;

/**
 * A goal as it stands on one day: what was written, what remains, today's quota and the quota of each writing day
 * left. The quotas share what remained at the start of the day; what is written today counts toward today's quota and
 * reshapes the days after from tomorrow on.
 */
final readonly class Plan
{
    /**
     * @param list<DailyQuota> $quotas the writing days from today to the deadline, each with its quota
     */
    public function __construct(
        public Goal $goal,
        public Day $today,
        public int $written,
        public int $writtenToday,
        public int $todayQuota,
        public array $quotas,
    ) {
    }

    /**
     * What remains to reach the target, 0 once it is reached.
     */
    public function remaining(): int
    {
        return max(0, $this->goal->target - $this->written);
    }

    /**
     * What remains of today's quota, 0 once it is met.
     */
    public function leftToday(): int
    {
        return max(0, $this->todayQuota - $this->writtenToday);
    }

    public function isReached(): bool
    {
        return $this->written >= $this->goal->target;
    }

    /**
     * Whether the deadline has passed with the target not reached: the author extends the deadline or lowers the target,
     * nothing is lost.
     */
    public function isOverdue(): bool
    {
        return !$this->isReached() && $this->today->isAfter($this->goal->deadline);
    }

    /**
     * The share of the target written, 0 to 100, rounded down.
     */
    public function percent(): int
    {
        return min(100, intdiv($this->written * 100, $this->goal->target));
    }
}
