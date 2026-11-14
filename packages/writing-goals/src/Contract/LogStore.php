<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Contract;

use Tilttek\WritingGoals\Day;
use Tilttek\WritingGoals\Progress;

/**
 * Where the counts logged toward each goal are kept, one per day, with the notice of the goal reached while it is due.
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
     * Notes the goal reached on that day, its `GoalReached` due until `markDelivered()`; reached again before, the
     * latest day is the one due.
     */
    public function markReached(string $goal, Day $day): void;

    /**
     * The day of the goal's `GoalReached` still due, or null.
     */
    public function undelivered(string $goal): ?Day;

    /**
     * Notes the goal's `GoalReached` of that day dispatched without an exception; nothing when the notice due is of
     * another day, or none is.
     */
    public function markDelivered(string $goal, Day $day): void;

    /**
     * Removes every count of the goal, and its notice due.
     */
    public function forget(string $goal): void;
}
