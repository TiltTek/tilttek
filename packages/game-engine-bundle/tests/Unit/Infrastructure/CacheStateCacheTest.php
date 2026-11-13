<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Tilttek\GameEngine\Engine;
use Tilttek\GameEngine\Games;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Snapshot;
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\Tables;
use Tilttek\GameEngineBundle\Infrastructure\Cache\CacheStateCache;
use Tilttek\GameEngineBundle\Tests\Fixture\InterleavingPool;
use Tilttek\GameEngineBundle\Tests\Fixture\RecordingLockFactory;
use Tilttek\GameEngineBundle\Tests\Fixture\RollRace;

#[CoversClass(CacheStateCache::class)]
final class CacheStateCacheTest extends TestCase
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    public function testAStateIsKeptAsJsonTextAndReadBack(): void
    {
        $pool = new ArrayAdapter();
        $cache = new CacheStateCache($pool);
        $state = $this->state();

        self::assertNull($cache->get('table:1'));
        $cache->put($state);

        $item = $pool->getItem(CacheStateCache::key('table:1'));
        self::assertTrue($item->isHit());
        self::assertIsString($item->get());
        self::assertSame($state->toArray(), json_decode($item->get(), true));
        self::assertSame(['revision', 'commitment', 'state'], array_keys($state->toArray()));
        self::assertEquals($state, $cache->get('table:1'));
        self::assertNotSame($state, $cache->get('table:1'));
        self::assertSame('tilttek_game_engine.state.'.hash('xxh128', 'table:1'), CacheStateCache::key('table:1'));

        $cache->forget('table:1');
        self::assertNull($cache->get('table:1'));
        self::assertFalse($pool->hasItem(CacheStateCache::key('table:1')));
    }

    public function testAMissIsNotForgotten(): void
    {
        $pool = new class extends ArrayAdapter {
            public int $deleted = 0;

            public function deleteItem(mixed $key): bool
            {
                ++$this->deleted;

                return parent::deleteItem($key);
            }
        };

        self::assertNull(new CacheStateCache($pool)->get('table:1'));
        self::assertSame(0, $pool->deleted);
    }

    public function testAStateLivesAsLongAsItsLifetime(): void
    {
        $clock = new MockClock();
        $pool = new ArrayAdapter(clock: $clock);
        new CacheStateCache($pool, 10)->put($this->state());
        new CacheStateCache($pool)->put($this->state('table:2'));
        $clock->sleep(9);

        self::assertInstanceOf(Snapshot::class, new CacheStateCache($pool)->get('table:1'));
        $clock->sleep(2);
        self::assertNull(new CacheStateCache($pool)->get('table:1'));
        $clock->sleep(100_000);
        self::assertInstanceOf(Snapshot::class, new CacheStateCache($pool)->get('table:2'));
    }

    public function testWhatThePoolGivesBackIsCheckedAndForgottenWhenRefused(): void
    {
        $pool = new ArrayAdapter();
        $cache = new CacheStateCache($pool);
        $key = CacheStateCache::key('table:1');
        $other = $this->state('table:2');

        foreach ([['an', 'array'], 'not json', '"text"', '{"game_id": 3}', (string) json_encode($other->state->toArray()), (string) json_encode($other->toArray())] as $value) {
            $pool->save($pool->getItem($key)->set($value));

            self::assertNull($cache->get('table:1'));
            self::assertFalse($pool->hasItem($key));
        }
    }

    public function testAStateJsonWouldNotGiveBackIsNotCached(): void
    {
        $pool = new ArrayAdapter();
        $cache = new CacheStateCache($pool);
        $snapshot = $this->state();
        $with = static fn (array $data): Snapshot => new Snapshot($snapshot->state->withData($data), $snapshot->revision, $snapshot->commitment);
        $cache->put($snapshot);

        $cache->put($with(['object' => new stdClass()]));
        self::assertFalse($pool->hasItem(CacheStateCache::key('table:1')));

        $cache->put($snapshot);
        $cache->put($with(['bad' => "\xC3\x28"]));
        self::assertFalse($pool->hasItem(CacheStateCache::key('table:1')));

        $cache->put($with(['float' => 1.0, 'list' => [1, 2], 'map' => ['b' => 1, 'a' => 2], 'empty' => []]));
        self::assertSame(['float' => 1.0, 'list' => [1, 2], 'map' => ['b' => 1, 'a' => 2], 'empty' => []], $cache->get('table:1')?->state->data);

        // The snapshot is 5 levels around the game's data: 60 more is as deep as the cache keeps, 65 in all.
        $cache->put($with(['deep' => $this->nested(60)]));
        self::assertSame(['deep' => $this->nested(60)], $cache->get('table:1')?->state->data);
        $cache->put($with(['deep' => $this->nested(61)]));
        self::assertFalse($pool->hasItem(CacheStateCache::key('table:1')));
    }

    public function testALaterRevisionOfATableIsNotWrittenOver(): void
    {
        $pool = new ArrayAdapter();
        $cache = new CacheStateCache($pool);
        $earlier = $this->state();
        $later = new Snapshot($earlier->state, $earlier->revision + 2, $earlier->commitment);

        $cache->put($later);
        $cache->put($earlier);
        self::assertSame(3, $cache->get('table:1')?->revision);

        $cache->put(new Snapshot($earlier->state->withData(['same' => true]), 3, $earlier->commitment));
        self::assertSame(['same' => true], $cache->get('table:1')?->state->data);

        // A table opened again under the same identifier has another seed: its first state replaces the old one.
        $reopened = new Snapshot($earlier->state, 1, hash('sha256', 'ffeeddccbbaa99887766554433221100ffeeddccbbaa99887766554433221100'));
        $cache->put($reopened);
        self::assertEquals($reopened, $cache->get('table:1'));

        // What the pool holds and cannot be read is written over.
        $pool->save($pool->getItem(CacheStateCache::key('table:1'))->set('not json'));
        $cache->put($earlier);
        self::assertEquals($earlier, $cache->get('table:1'));
    }

    public function testAStaleStateWrittenWhileALaterOneIsWrittenNeverLandsOverIt(): void
    {
        $earlier = $this->state();
        $later = new Snapshot($earlier->state, $earlier->revision + 2, $earlier->commitment);

        // Without a lock, PSR-6 lets a stale writer read before a later write and write after it.
        $pool = new InterleavingPool();
        $stale = new CacheStateCache($pool);
        $pool->beforeSave = static function () use ($pool, $later): void {
            new CacheStateCache($pool)->put($later);
        };
        $stale->put($earlier);
        self::assertSame([3, 1], $pool->saved);

        // With one, the second writer finds the lock of the key held and leaves the cache as it is: no revision written
        // ever goes down, whichever process comes first.
        foreach ([[$earlier, $later], [$later, $earlier]] as [$first, $second]) {
            $locks = new LockFactory(new InMemoryStore());
            $pool = new InterleavingPool();
            $pool->beforeSave = static function () use ($pool, $second, $locks): void {
                new CacheStateCache($pool, null, $locks)->put($second);
            };
            new CacheStateCache($pool, null, $locks)->put($first);
            self::assertSame([$first->revision], $pool->saved);

            // The lock is released after the write: the later state lands, and the stale one stays out.
            new CacheStateCache($pool, null, $locks)->put($later);
            new CacheStateCache($pool, null, $locks)->put($earlier);
            self::assertSame(3, new CacheStateCache($pool)->get('table:1')?->revision);
            self::assertSame($first === $earlier ? [1, 3] : [3, 3], $pool->saved);
            self::assertTrue($locks->createLock(CacheStateCache::key('table:1'))->acquire(false));
        }

        // A lock held by another process keeps the write out; the lock of a write lives as long as `LOCK_TTL`.
        $locks = new RecordingLockFactory();
        $held = $locks->createLock(CacheStateCache::key('table:1'));
        $held->acquire();

        $pool = new InterleavingPool();
        new CacheStateCache($pool, null, $locks)->put($earlier);
        self::assertSame([], $pool->saved);
        self::assertSame(5.0, CacheStateCache::LOCK_TTL);
        self::assertSame(CacheStateCache::LOCK_TTL, $locks->ttl);
    }

    public function testTheLockOfAWriteIsReleasedWhateverTheWriteDoes(): void
    {
        $snapshot = $this->state();
        $locks = new RecordingLockFactory();
        $pool = new InterleavingPool();

        new CacheStateCache($pool, null, $locks)->put($snapshot);
        self::assertCount(1, $locks->created);
        self::assertTrue(new LockFactory(new InMemoryStore())->createLock('x')->acquire(false));
        self::assertTrue($locks->createLock(CacheStateCache::key('table:1'))->acquire(false));

        $locks = new RecordingLockFactory();
        $pool = new InterleavingPool();
        $pool->beforeSave = static function (): never {
            throw new RuntimeException('The pool is down.');
        };

        try {
            new CacheStateCache($pool, null, $locks)->put($snapshot);
            self::fail('A write that failed was hidden.');
        } catch (RuntimeException) {
            self::assertTrue($locks->createLock(CacheStateCache::key('table:1'))->acquire(false));
        }
    }

    public function testTablesReadTheirStatesFromTheCache(): void
    {
        $pool = new ArrayAdapter();
        $logs = new InMemoryLogStore();
        $tables = new Tables(new Games(new RollRace()), $logs, new CacheStateCache($pool));
        $tables->open('roll-race', 'table:1', ['ann', 'bob'], [], self::SEED);

        $played = $tables->play('table:1', new Move('roll.roll', 'ann'));

        self::assertEquals($played, new Tables(new Games(new RollRace()), $logs, new CacheStateCache($pool))->state('table:1'));
        self::assertSame(1, new CacheStateCache($pool)->get('table:1')?->revision);
    }

    /**
     * @return array<mixed>
     */
    private function nested(int $levels): array
    {
        $nested = [];

        for ($level = 0; $level < $levels; ++$level) {
            $nested = [$nested];
        }

        return $nested;
    }

    private function state(string $gameId = 'table:1'): Snapshot
    {
        $race = new RollRace();
        $engine = new Engine();
        $table = $engine->play($race, $engine->start($race, $gameId, ['ann', 'bob'], [], self::SEED), new Move('roll.roll', 'ann'));

        return Snapshot::of($table->state, $table->log->header());
    }
}
