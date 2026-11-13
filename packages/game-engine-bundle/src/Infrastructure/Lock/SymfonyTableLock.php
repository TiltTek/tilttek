<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Infrastructure\Lock;

use Closure;
use Override;
use Symfony\Component\Lock\LockFactory;
use Tilttek\GameEngineBundle\Application\Contract\TableLock;

/**
 * The lock of a table on Symfony's lock factory: one lock per table, named from a hash of its identifier, waited for,
 * and released when the work ends. Its time to live bounds how long a process that died keeps the others waiting.
 */
final readonly class SymfonyTableLock implements TableLock
{
    /**
     * @param float $ttl the seconds a lock is kept when the process that holds it does not release it
     */
    public function __construct(
        private LockFactory $factory,
        private float $ttl = 30.0,
    ) {
    }

    #[Override]
    public function hold(string $gameId, Closure $work): mixed
    {
        $lock = $this->factory->createLock(self::resource($gameId), $this->ttl);
        $lock->acquire(true);

        try {
            return $work();
        } finally {
            $lock->release();
        }
    }

    /**
     * The name of the lock of a table: its identifier hashed, so that it fits any lock store.
     */
    public static function resource(string $gameId): string
    {
        return 'tilttek_game_engine.table.'.hash('xxh128', $gameId);
    }
}
