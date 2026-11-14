<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Store;

use Override;
use Tilttek\WritingGoals\Contract\LogStore;
use Tilttek\WritingGoals\Day;
use Tilttek\WritingGoals\Progress;

/**
 * The counts in memory, forgotten with the process: for tests and for an application that keeps them elsewhere.
 */
final class InMemoryLogStore implements LogStore
{
    /**
     * @var array<string, Progress>
     */
    private array $progress = [];

    /**
     * @var array<string, Day> the day of each goal's notice still due
     */
    private array $due = [];

    #[Override]
    public function progress(string $goal): Progress
    {
        return $this->progress[$goal] ?? Progress::none();
    }

    #[Override]
    public function add(string $goal, Day $day, int $delta): int
    {
        $progress = $this->progress($goal);
        $count = min(Progress::MAX_PER_DAY, max(0, $progress->on($day) + $delta));
        $this->progress[$goal] = $progress->with($day, $count);

        return $count;
    }

    #[Override]
    public function set(string $goal, Day $day, int $count): void
    {
        $this->progress[$goal] = $this->progress($goal)->with($day, $count);
    }

    #[Override]
    public function markReached(string $goal, Day $day): void
    {
        $this->due[$goal] = $day;
    }

    #[Override]
    public function undelivered(string $goal): ?Day
    {
        return $this->due[$goal] ?? null;
    }

    #[Override]
    public function markDelivered(string $goal, Day $day): void
    {
        if ($this->undelivered($goal)?->toString() === $day->toString()) {
            unset($this->due[$goal]);
        }
    }

    #[Override]
    public function forget(string $goal): void
    {
        unset($this->progress[$goal], $this->due[$goal]);
    }
}
