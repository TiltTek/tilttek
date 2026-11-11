<?php

declare(strict_types=1);

namespace Tilttek\Collections\Daily;

use Override;

/**
 * The calendar of the item of the day, in memory.
 */
final class InMemoryDailyStore implements DailyStore
{
    /**
     * @var array<string, DailyPick>
     */
    private array $picks = [];

    #[Override]
    public function save(DailyPick $pick): void
    {
        $this->picks[$pick->day] = $pick;
    }

    #[Override]
    public function find(string $day): ?DailyPick
    {
        return $this->picks[$day] ?? null;
    }

    #[Override]
    public function between(string $from, string $until): array
    {
        $picks = array_filter($this->picks, static fn (DailyPick $pick): bool => $pick->day >= $from && $pick->day <= $until);
        usort($picks, static fn (DailyPick $a, DailyPick $b): int => $a->day <=> $b->day);

        return $picks;
    }

    #[Override]
    public function remove(string $day): bool
    {
        $there = isset($this->picks[$day]);
        unset($this->picks[$day]);

        return $there;
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->picks = [];
    }
}
