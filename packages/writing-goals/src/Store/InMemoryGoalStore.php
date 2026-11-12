<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\WritingGoals\Contract\GoalStore;
use Tilttek\WritingGoals\Goal;

/**
 * The goals in memory, forgotten with the process: for tests and for an application that keeps them elsewhere.
 */
final class InMemoryGoalStore implements GoalStore
{
    /**
     * @var array<string, Goal>
     */
    private array $goals = [];

    #[Override]
    public function save(Goal $goal): void
    {
        $this->goals[$goal->id] = $goal;
    }

    #[Override]
    public function find(string $id): ?Goal
    {
        return $this->goals[$id] ?? null;
    }

    #[Override]
    public function goalsOf(ResourceReference $author): array
    {
        $goals = array_filter($this->goals, static fn (Goal $goal): bool => $goal->author->equals($author));
        ksort($goals, \SORT_STRING);

        return array_values($goals);
    }

    #[Override]
    public function remove(string $id): void
    {
        unset($this->goals[$id]);
    }
}
