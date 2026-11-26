<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Store;

use Override;
use Tilttek\TurnClock\Contract\ClockStore;
use Tilttek\TurnClock\Exception\ClockConflict;
use Tilttek\TurnClock\TableClock;

/**
 * The clocks in memory, for tests: they last as long as the object.
 */
final class InMemoryClockStore implements ClockStore
{
    /** @var array<string, TableClock> by identifier */
    private array $clocks = [];

    #[Override]
    public function clock(string $id): ?TableClock
    {
        return $this->clocks[$id] ?? null;
    }

    #[Override]
    public function save(TableClock $clock): TableClock
    {
        if ($clock->version !== ($this->clocks[$clock->id]->version ?? 0)) {
            throw ClockConflict::changed($clock->id);
        }

        return $this->clocks[$clock->id] = $clock->withVersion($clock->version + 1);
    }
}
