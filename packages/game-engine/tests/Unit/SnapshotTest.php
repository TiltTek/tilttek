<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Engine;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\LogHeader;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Snapshot;
use Tilttek\GameEngine\Store\InMemoryStateCache;
use Tilttek\GameEngine\Tests\Unit\Fixture\Race;

/**
 * The header of a log, read without its moves, and the snapshot of a state, which a cache keeps only for the revision
 * and the seed it was built from.
 */
#[CoversClass(LogHeader::class)]
#[CoversClass(Snapshot::class)]
#[CoversClass(InMemoryStateCache::class)]
final class SnapshotTest extends TestCase
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    private const string OTHER_SEED = 'ffeeddccbbaa99887766554433221100ffeeddccbbaa99887766554433221100';

    public function testAHeaderCountsTheMovesAndTheChangesOfItsLog(): void
    {
        $log = new GameLog('table:1', 'race', '1', self::SEED, ['ann', 'bob'], ['target' => 10]);
        $header = $log->header();

        self::assertSame([0, 0, 0], [$header->sequence, $header->revision, $header->lineage()]);
        self::assertSame($log->commitment(), $header->commitment());
        self::assertSame(['table:1', 'race', '1', self::SEED, ['ann', 'bob'], ['target' => 10]], [$header->gameId, $header->game, $header->version, $header->seed, $header->seats, $header->options]);

        $appended = $header->appended()->appended();
        self::assertSame([2, 2, 0], [$appended->sequence, $appended->revision, $appended->lineage()]);
        $taken = $appended->withoutLast();
        self::assertSame([1, 3, 2], [$taken->sequence, $taken->revision, $taken->lineage()]);
        self::assertEquals($header->seats, $taken->seats);
        self::assertSame(5, $log->header(5)->revision);
        self::assertSame(GameLog::MAX_MOVES, new LogHeader('t', 'race', '1', self::SEED, [], [], GameLog::MAX_MOVES, GameLog::MAX_MOVES)->sequence);

        foreach ([
            [$header->withoutLast(...), 'The log of the table "table:1" holds -1 moves, not 0 to 100000.'],
            [static fn (): mixed => new LogHeader('t', 'race', '1', self::SEED, [], [], GameLog::MAX_MOVES + 1, GameLog::MAX_MOVES + 1), 'The log of the table "t" holds 100001 moves, not 0 to 100000.'],
            [static fn (): mixed => new LogHeader('t', 'race', '1', self::SEED, [], [], 2, 1), 'The log of the table "t" is at the revision 1, fewer than its 2 moves.'],
            [static fn (): mixed => $log->header(-1), 'The log of the table "table:1" is at the revision -1, fewer than its 0 moves.'],
            [static fn (): mixed => new LogHeader('1t', 'race', '1', self::SEED, [], [], 0, 0), 'The identifier of a table "1t" must be an ASCII letter followed by up to 127 ASCII letters, digits, ":", ".", "_" or "-".'],
            [static fn (): mixed => new LogHeader('t', 'Race', '1', self::SEED, [], [], 0, 0), 'The name of a game "Race" must be 1 to 64 lowercase letters and digits, words joined by "-" or "_".'],
            [static fn (): mixed => new LogHeader('t', 'race', '1', 'seed', [], [], 0, 0), 'A seed is 64 lowercase hexadecimal digits.'],
        ] as [$call, $message]) {
            try {
                $call();
                self::fail('The header was accepted.');
            } catch (InvalidGame $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testASnapshotReflectsOrPrecedesTheLogItWasBuiltFrom(): void
    {
        $engine = new Engine();
        $race = new Race();
        $opened = $engine->start($race, 'table:1', ['ann', 'bob'], [], self::SEED);
        $first = $engine->play($race, $opened, new Move('race.step', 'ann', ['steps' => 2]));
        $header = $first->log->header();
        $snapshot = Snapshot::of($first->state, $header);

        self::assertSame([1, $header->commitment()], [$snapshot->revision, $snapshot->commitment]);
        self::assertTrue($snapshot->reflects($header));
        self::assertFalse($snapshot->precedes($header));
        self::assertFalse($snapshot->reflects($header->appended()));
        self::assertTrue($snapshot->precedes($header->appended()));
        self::assertTrue($snapshot->precedes($header->appended()->appended()));

        // A move taken back and another made: same number of moves, another revision and lineage.
        $replaced = $header->withoutLast()->appended();
        self::assertSame(1, $replaced->sequence);
        self::assertFalse($snapshot->reflects($replaced));
        self::assertFalse($snapshot->precedes($replaced));
        self::assertFalse($snapshot->precedes($replaced->appended()));
        self::assertFalse(Snapshot::of($opened->state, $opened->log->header())->precedes($replaced));

        // Another seed, another table, another game: never.
        $reseeded = new LogHeader('table:1', 'race', '1', self::OTHER_SEED, ['ann', 'bob'], [], 1, 1);
        self::assertFalse($snapshot->reflects($reseeded));
        self::assertFalse($snapshot->precedes($reseeded->appended()));
        self::assertFalse($snapshot->reflects(new LogHeader('table:2', 'race', '1', self::SEED, ['ann', 'bob'], [], 1, 1)));
        self::assertFalse($snapshot->precedes(new LogHeader('table:2', 'race', '1', self::SEED, ['ann', 'bob'], [], 2, 2)));
        self::assertFalse($snapshot->reflects(new LogHeader('table:1', 'chess', '1', self::SEED, ['ann', 'bob'], [], 1, 1)));
        self::assertFalse($snapshot->precedes(new LogHeader('table:1', 'chess', '1', self::SEED, ['ann', 'bob'], [], 2, 2)));
        self::assertFalse(new Snapshot($first->state, 3, $header->commitment())->reflects($header));
    }

    public function testASnapshotSupersedesAnEarlierRevisionOfTheSameTableOnly(): void
    {
        $state = $this->state();
        $commitment = hash('sha256', self::SEED);
        $earlier = new Snapshot($state, 1, $commitment);
        $later = new Snapshot($state, 3, $commitment);

        self::assertTrue($later->supersedes($earlier));
        self::assertFalse($earlier->supersedes($later));
        self::assertFalse($later->supersedes($later));
        self::assertFalse($later->supersedes(new Snapshot($state, 1, hash('sha256', self::OTHER_SEED))));
        self::assertFalse($later->supersedes(new Snapshot(new GameState('table:2', 'race', 1, $state->seats, $state->options, $state->phase, $state->turn), 1, $commitment)));

        $cache = new InMemoryStateCache();
        $cache->put($later);
        $cache->put($earlier);
        self::assertSame($later, $cache->get('table:1'));
        $cache->put($again = new Snapshot($state, 3, $commitment));
        self::assertSame($again, $cache->get('table:1'));

        // A table opened again under the same identifier has another seed: its snapshots replace the old ones.
        $cache->put($reopened = new Snapshot($state, 1, hash('sha256', self::OTHER_SEED)));
        self::assertSame($reopened, $cache->get('table:1'));
        $cache->forget('table:1');
        self::assertNull($cache->get('table:1'));
        $cache->put($later);
        $cache->reset();
        self::assertNull($cache->get('table:1'));
    }

    public function testASnapshotIsWrittenAndReadBackAsPlainValues(): void
    {
        $state = $this->state();
        $snapshot = new Snapshot($state, 3, hash('sha256', self::SEED));
        $array = $snapshot->toArray();

        self::assertSame(['revision' => 3, 'commitment' => hash('sha256', self::SEED), 'state' => $state->toArray()], $array);
        self::assertEquals($snapshot, Snapshot::fromArray($array));

        foreach ([
            [['revision' => 0] + $array, 'The state of the table "table:1" is at the revision 0, fewer than its 1 moves.'],
            [['commitment' => str_repeat('A', 64)] + $array, 'The commitment of a cached state is 64 lowercase hexadecimal digits.'],
            [['commitment' => str_repeat('a', 63)] + $array, 'The commitment of a cached state is 64 lowercase hexadecimal digits.'],
            [['commitment' => str_repeat('a', 65)] + $array, 'The commitment of a cached state is 64 lowercase hexadecimal digits.'],
            [['commitment' => str_repeat('a', 64)."\n"] + $array, 'The commitment of a cached state is 64 lowercase hexadecimal digits.'],
            [['commitment' => "\n".str_repeat('a', 64)] + $array, 'The commitment of a cached state is 64 lowercase hexadecimal digits.'],
            [['revision' => '3'] + $array, 'The field "revision" of a cached state is not a whole number of 0 or more.'],
            [['state' => 'state'] + $array, 'The field "state" of a cached state is not a mapping.'],
        ] as [$data, $message]) {
            try {
                Snapshot::fromArray($data);
                self::fail('The snapshot was read.');
            } catch (InvalidGame $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        $this->expectExceptionMessageIsOrContains('The state of the table "table:1" after 1 moves is not the one of the log of the table "table:1" after 0 moves.');

        Snapshot::of($state, new GameLog('table:1', 'race', '1', self::SEED, ['ann', 'bob'])->header());
    }

    public function testASnapshotOfAnotherTableIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The state of the table "table:1" after 1 moves is not the one of the log of the table "table:2" after 1 moves.');

        Snapshot::of($this->state(), new LogHeader('table:2', 'race', '1', self::SEED, ['ann', 'bob'], [], 1, 1));
    }

    private function state(): GameState
    {
        $engine = new Engine();
        $race = new Race();

        return $engine->play($race, $engine->start($race, 'table:1', ['ann', 'bob'], [], self::SEED), new Move('race.step', 'ann', ['steps' => 2]))->state;
    }
}
