<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Contract;

use Tilttek\TurnClock\Exception\ClockConflict;
use Tilttek\TurnClock\TableClock;

/**
 * Keeps the clocks, each at a version.
 */
interface ClockStore
{
    /**
     * The clock, or null when none has the identifier.
     */
    public function clock(string $id): ?TableClock;

    /**
     * Keeps the clock at the next version, and returns it. A clock at version 0 is new; any other is kept only when the
     * store holds it at that version.
     *
     * @throws ClockConflict when the clock is new and the store has one of its identifier, or the store holds it at
     *                       another version; nothing is kept
     */
    public function save(TableClock $clock): TableClock;
}
