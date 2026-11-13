<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Infrastructure\Cache;

use JsonException;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Lock\LockFactory;
use Tilttek\GameEngine\Contract\StateCache;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\Snapshot;

/**
 * The current states of the tables in a PSR-6 cache pool shared by every process — `cache.app`, or a pool on Valkey or
 * Doctrine DBAL —, each kept with the revision of its log and the commitment to its seed as the JSON text of
 * `Snapshot::toArray()`, under a key hashed from the table's identifier.
 *
 * The pool keeps a text, never a PHP object, so that nothing it gives back is unserialized into one; a state whose data
 * JSON would not give back as it is — an object in the game's data — is not cached at all, and every read replays its
 * log. Whatever the pool gives back is read by `Snapshot::fromArray()`, and anything it refuses is forgotten: the
 * library replays the log instead.
 *
 * A snapshot is written over one of a later revision of the same table only when it is not of the same seed: a process
 * that read the log before a change keeps its stale state to itself. PSR-6 has no compare-and-set: with a lock factory,
 * the read of the kept snapshot and the write are made while holding the lock of the key, never waited for — a writer
 * that finds it held leaves the cache as it is —, so that a stale state never lands over a later one. Without one, a
 * stale write may still land between the read and the write of another; the library then sees that its revision is not
 * the log's, never serves it, and caches the state of the log in its place.
 */
final readonly class CacheStateCache implements StateCache
{
    private const int JSON_DEPTH = 65;

    /** The seconds the lock of a write is kept when the process that holds it does not release it. */
    public const float LOCK_TTL = 5.0;

    /**
     * @param ?int         $lifetime the seconds a state is kept after it was cached; as long as the pool keeps it when null
     * @param ?LockFactory $locks    the locks a write is made under, shared by every process; none when null
     */
    public function __construct(
        private CacheItemPoolInterface $pool,
        private ?int $lifetime = null,
        private ?LockFactory $locks = null,
    ) {
    }

    #[Override]
    public function get(string $gameId): ?Snapshot
    {
        $item = $this->pool->getItem(self::key($gameId));

        if (!$item->isHit()) {
            return null;
        }

        $snapshot = $this->read($item->get());

        if (!$snapshot instanceof Snapshot || $snapshot->state->gameId !== $gameId) {
            $this->forget($gameId);

            return null;
        }

        return $snapshot;
    }

    #[Override]
    public function put(Snapshot $snapshot): void
    {
        if (!$this->locks instanceof LockFactory) {
            $this->write($snapshot);

            return;
        }

        $lock = $this->locks->createLock(self::key($snapshot->state->gameId), self::LOCK_TTL);

        if (!$lock->acquire(false)) {
            return;
        }

        try {
            $this->write($snapshot);
        } finally {
            $lock->release();
        }
    }

    #[Override]
    public function forget(string $gameId): void
    {
        $this->pool->deleteItem(self::key($gameId));
    }

    /**
     * The key of a table's state: its identifier may hold characters PSR-6 reserves, which a hash removes.
     */
    public static function key(string $gameId): string
    {
        return 'tilttek_game_engine.state.'.hash('xxh128', $gameId);
    }

    private function write(Snapshot $snapshot): void
    {
        $gameId = $snapshot->state->gameId;
        $item = $this->pool->getItem(self::key($gameId));
        $kept = $item->isHit() ? $this->read($item->get()) : null;

        if ($kept instanceof Snapshot && $kept->supersedes($snapshot)) {
            return;
        }

        $array = $snapshot->toArray();

        try {
            $json = json_encode($array, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION, self::JSON_DEPTH);
            $faithful = json_decode($json, true, self::JSON_DEPTH, \JSON_THROW_ON_ERROR) === $array;
        } catch (JsonException) {
            $faithful = false;
        }

        if (!$faithful) {
            $this->forget($gameId);

            return;
        }

        $this->pool->save($item->set($json)->expiresAfter($this->lifetime));
    }

    private function read(mixed $json): ?Snapshot
    {
        try {
            $data = \is_string($json) ? json_decode($json, true, self::JSON_DEPTH, \JSON_THROW_ON_ERROR) : null;

            return \is_array($data) ? Snapshot::fromArray($data) : null;
        } catch (JsonException|InvalidGame) {
            return null;
        }
    }
}
