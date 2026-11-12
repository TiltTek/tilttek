<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Contract;

use Tilttek\WritingGoals\Day;
use Tilttek\WritingGoals\Progress;

/**
 * Where the counts logged toward each goal are kept, one per day.
 */
interface LogStore
{
    public function progress(string $goal): Progress;

    /**
     * Adds to the count of a day in one step — two requests adding at once both count —, never below 0 and never above
     * `Progress::MAX_PER_DAY`.
     *
     * @return int the day's count after the addition
     */
    public function add(string $goal, Day $day, int $delta): int;

    /**
     * Replaces the count of a day.
     */
    public function set(string $goal, Day $day, int $count): void;

    /**
     * Removes every count of the goal.
     */
    public function forget(string $goal): void;
}
