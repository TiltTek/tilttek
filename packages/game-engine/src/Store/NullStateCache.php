<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Store;

use Tilttek\GameEngine\Contract\StateCache;
use Tilttek\GameEngine\Snapshot;

/**
 * No cache: every read replays the log.
 */
final readonly class NullStateCache implements StateCache
{
    public function get(string $gameId): ?Snapshot
    {
        return null;
    }

    public function put(Snapshot $snapshot): void
    {
    }

    public function forget(string $gameId): void
    {
    }
}
