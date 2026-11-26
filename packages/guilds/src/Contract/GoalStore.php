<?php

declare(strict_types=1);

namespace Tilttek\Guilds\Contract;

use Tilttek\Guilds\Contribution;
use Tilttek\Guilds\Goal;

/**
 * Where the goals of the guilds and their contributions are kept. Guilds and members are named by their references
 * written `kind:identifier`.
 *
 * An application that serves concurrent requests runs each contribution in a transaction, or under a lock on the
 * goal, so that the goal is reached once. An implementation keeps no state between two requests beside its storage.
 */
interface GoalStore
{
    public function goal(string $key): ?Goal;

    /**
     * Writes the goal, replacing the one of the same key.
     */
    public function saveGoal(Goal $goal): void;

    /**
     * The guild's goals, the earliest opened first, ties in the order of their keys.
     *
     * @return list<Goal>
     */
    public function goalsOf(string $guild): array;

    public function contribution(string $goal, string $key): ?Contribution;

    public function addContribution(Contribution $contribution): void;

    /**
     * The points of every contribution to the goal.
     */
    public function points(string $goal): int;

    /**
     * The points the member brought to the goal.
     */
    public function pointsOf(string $goal, string $member): int;

    /**
     * How many distinct members brought points to the goal, the erased ones counted as one together.
     */
    public function contributors(string $goal): int;

    /**
     * Forgets the member on every contribution they made, the points kept.
     */
    public function forget(string $member): void;
}
