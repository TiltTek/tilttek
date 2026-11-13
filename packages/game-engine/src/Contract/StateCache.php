<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Contract;

use Tilttek\GameEngine\Snapshot;

/**
 * Where the current state of the tables is cached, to spare a replay of the log at each read: each state with the
 * revision of the log it reflects and the commitment to the seed of its table. A state it gives is used only when both
 * are those of the log; anything it forgets, or gives stale, is rebuilt.
 */
interface StateCache
{
    public function get(string $gameId): ?Snapshot;

    /**
     * Keeps a snapshot, unless the cache holds one of a later revision of the same table, which a process that read the
     * log before a change would otherwise write over.
     */
    public function put(Snapshot $snapshot): void;

    public function forget(string $gameId): void;
}
