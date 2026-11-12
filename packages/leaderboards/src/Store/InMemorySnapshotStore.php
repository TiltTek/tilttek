<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\Placing;
use Tilttek\Leaderboards\Snapshot;

/**
 * The snapshots in memory, forgotten with the process: for tests, scripts and examples.
 */
final class InMemorySnapshotStore implements SnapshotStore
{
    /**
     * @var array<string, Snapshot> by board and period
     */
    private array $snapshots = [];

    #[Override]
    public function save(Snapshot $snapshot): bool
    {
        $key = json_encode([$snapshot->board, $snapshot->period], \JSON_THROW_ON_ERROR);

        if (isset($this->snapshots[$key])) {
            return false;
        }

        $this->snapshots[$key] = $snapshot;

        return true;
    }

    #[Override]
    public function find(string $board, string $period): ?Snapshot
    {
        return $this->snapshots[json_encode([$board, $period], \JSON_THROW_ON_ERROR)] ?? null;
    }

    #[Override]
    public function placings(ResourceReference $member): array
    {
        $placings = [];

        foreach ($this->snapshots as $snapshot) {
            foreach ($snapshot->placings as $placing) {
                if ($placing->member->equals($member)) {
                    $placings[] = $placing;
                }
            }
        }

        usort($placings, static fn (Placing $a, Placing $b): int => [$a->board, $a->period] <=> [$b->board, $b->period]);

        return $placings;
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $removed = 0;

        foreach ($this->snapshots as $key => $snapshot) {
            $kept = array_values(array_filter($snapshot->placings, static fn (Placing $placing): bool => !$placing->member->equals($member)));
            $removed += \count($snapshot->placings) - \count($kept);
            $this->snapshots[$key] = new Snapshot($snapshot->board, $snapshot->period, $snapshot->takenAt, $snapshot->participants, $kept);
        }

        return $removed;
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->snapshots = [];
    }
}
