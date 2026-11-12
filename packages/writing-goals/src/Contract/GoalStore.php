<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\WritingGoals\Goal;

/**
 * Where the goals are kept.
 */
interface GoalStore
{
    /**
     * Keeps the goal, replacing the one of the same identifier.
     */
    public function save(Goal $goal): void;

    public function find(string $id): ?Goal;

    /**
     * @return list<Goal> the author's goals, by identifier
     */
    public function goalsOf(ResourceReference $author): array;

    public function remove(string $id): void;
}
