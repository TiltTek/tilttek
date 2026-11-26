<?php

declare(strict_types=1);

namespace Tilttek\Guilds\Store;

use Tilttek\Guilds\Contract\GoalStore;
use Tilttek\Guilds\Contribution;
use Tilttek\Guilds\Goal;

/**
 * The goals and their contributions kept in memory, for tests and for an application that rebuilds them at each start.
 */
final class InMemoryGoalStore implements GoalStore
{
    /**
     * @var array<string, Goal>
     */
    private array $goals = [];

    /**
     * @var array<string, list<Contribution>> by goal
     */
    private array $contributions = [];

    public function goal(string $key): ?Goal
    {
        return $this->goals[$key] ?? null;
    }

    public function saveGoal(Goal $goal): void
    {
        $this->goals[$goal->key] = $goal;
    }

    public function goalsOf(string $guild): array
    {
        $found = array_filter($this->goals, static fn (Goal $goal): bool => $goal->guild->toString() === $guild);
        usort($found, static function (Goal $a, Goal $b): int {
            $byTime = $a->openedAt <=> $b->openedAt;

            return 0 !== $byTime ? $byTime : strcmp($a->key, $b->key);
        });

        return $found;
    }

    public function contribution(string $goal, string $key): ?Contribution
    {
        foreach ($this->contributions[$goal] ?? [] as $contribution) {
            if ($contribution->key === $key) {
                return $contribution;
            }
        }

        return null;
    }

    public function addContribution(Contribution $contribution): void
    {
        $this->contributions[$contribution->goal][] = $contribution;
    }

    public function points(string $goal): int
    {
        return array_sum(array_map(static fn (Contribution $contribution): int => $contribution->points, $this->contributions[$goal] ?? []));
    }

    public function pointsOf(string $goal, string $member): int
    {
        $points = 0;

        foreach ($this->contributions[$goal] ?? [] as $contribution) {
            if ($contribution->member?->toString() === $member) {
                $points += $contribution->points;
            }
        }

        return $points;
    }

    public function contributors(string $goal): int
    {
        return \count(array_unique(array_map(static fn (Contribution $contribution): string => $contribution->member?->toString() ?? '', $this->contributions[$goal] ?? [])));
    }

    public function forget(string $member): void
    {
        foreach ($this->contributions as $goal => $contributions) {
            $this->contributions[$goal] = array_map(
                static fn (Contribution $contribution): Contribution => $contribution->member?->toString() === $member ? new Contribution($contribution->goal, null, $contribution->points, $contribution->key, $contribution->at) : $contribution,
                $contributions,
            );
        }
    }
}
