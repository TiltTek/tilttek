<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Application\Contract;

use Closure;

/**
 * Keeps two moves at one table apart: the second waits for the first to land, then plays against the table as the first
 * left it. Without a lock, the log store still refuses the second with `ConcurrentMove`.
 */
interface TableLock
{
    /**
     * Runs the work while holding the lock of the table, and gives back what it returns.
     *
     * @template T
     *
     * @param Closure(): T $work
     *
     * @return T
     */
    public function hold(string $gameId, Closure $work): mixed;
}
