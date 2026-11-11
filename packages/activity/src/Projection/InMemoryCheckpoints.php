<?php

declare(strict_types=1);

namespace Tilttek\Activity\Projection;

use Override;

/**
 * The checkpoints in memory, for tests and for a process that keeps nothing.
 */
final class InMemoryCheckpoints implements Checkpoints
{
    /**
     * @var array<string, int>
     */
    private array $positions = [];

    #[Override]
    public function position(string $projection): int
    {
        return $this->positions[$projection] ?? 0;
    }

    #[Override]
    public function save(string $projection, int $position): void
    {
        $this->positions[$projection] = $position;
    }

    /**
     * Empties the checkpoints: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->positions = [];
    }
}
