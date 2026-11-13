<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Store;

use Tilttek\GameEngine\Contract\StateCache;
use Tilttek\GameEngine\Snapshot;

/**
 * The states in the memory of the process, for tests and scripts; a worker serving several requests resets it, or uses
 * a shared cache instead.
 */
final class InMemoryStateCache implements StateCache
{
    /** @var array<string, Snapshot> */
    private array $snapshots = [];

    public function get(string $gameId): ?Snapshot
    {
        return $this->snapshots[$gameId] ?? null;
    }

    public function put(Snapshot $snapshot): void
    {
        $gameId = $snapshot->state->gameId;
        $kept = $this->snapshots[$gameId] ?? null;

        if ($kept instanceof Snapshot && $kept->supersedes($snapshot)) {
            return;
        }

        $this->snapshots[$gameId] = $snapshot;
    }

    public function forget(string $gameId): void
    {
        unset($this->snapshots[$gameId]);
    }

    public function reset(): void
    {
        $this->snapshots = [];
    }
}
