<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Exception\ConcurrentMove;
use Tilttek\GameEngine\Exception\GameInProgress;
use Tilttek\GameEngine\Exception\GameOver;
use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\Exception\UnknownTable;
use Tilttek\GameEngine\Games;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\LoggedMove;
use Tilttek\GameEngine\LogHeader;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Scene\RoleplayingScene;
use Tilttek\GameEngine\Snapshot;
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\Store\InMemoryStateCache;
use Tilttek\GameEngine\Store\NullStateCache;
use Tilttek\GameEngine\Store\NullTablePublisher;
use Tilttek\GameEngine\Tables;
use Tilttek\GameEngine\TableUpdate;
use Tilttek\GameEngine\Tests\Unit\Fixture\CountingLogStore;
use Tilttek\GameEngine\Tests\Unit\Fixture\Race;
use Tilttek\GameEngine\Tests\Unit\Fixture\RecordingPublisher;

#[CoversClass(Tables::class)]
#[CoversClass(Games::class)]
#[CoversClass(TableUpdate::class)]
#[CoversClass(InMemoryLogStore::class)]
#[CoversClass(InMemoryStateCache::class)]
#[CoversClass(NullStateCache::class)]
#[CoversClass(NullTablePublisher::class)]
#[CoversClass(ConcurrentMove::class)]
#[CoversClass(UnknownTable::class)]
#[CoversClass(GameOver::class)]
#[CoversClass(GameInProgress::class)]
final class TablesTest extends TestCase
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    private Race $race;

    private CountingLogStore $logs;

    private InMemoryStateCache $cache;

    private RecordingPublisher $publisher;

    private Tables $tables;

    protected function setUp(): void
    {
        $this->race = new Race();
        $this->logs = new CountingLogStore();
        $this->cache = new InMemoryStateCache();
        $this->publisher = new RecordingPublisher();
        $this->tables = $this->tables();
    }

    public function testATableIsOpenedPlayedAndToldToItsPlayers(): void
    {
        $opened = $this->tables->open('race', 'table:1', ['ann', 'bob'], ['target' => 10], self::SEED);
        $played = $this->tables->play('table:1', new Move('race.step', 'ann', ['steps' => 3]), 0);

        self::assertSame(0, $opened->sequence);
        self::assertSame(1, $played->sequence);
        self::assertSame(1, $this->logs->inner->load('table:1')?->sequence());
        $cached = $this->cache->get('table:1');
        self::assertInstanceOf(Snapshot::class, $cached);
        self::assertSame($played, $cached->state);
        self::assertSame(1, $cached->revision);
        self::assertSame($this->tables->log('table:1')->commitment(), $cached->commitment);
        self::assertEquals([
            new TableUpdate('table:1', 0, 'race', 1, ['ann'], false),
            new TableUpdate('table:1', 1, 'race', 1, ['bob'], false),
        ], $this->publisher->updates);
        self::assertSame($played, $this->tables->state('table:1'));
        self::assertSame(1, $this->race->setups);

        for ($move = 0; $move < 6; ++$move) {
            $this->tables->play('table:1', new Move('race.step', $this->tables->state('table:1')->turn->toAct()[0], ['steps' => 3]));
        }

        $last = array_last($this->publisher->updates);
        self::assertEquals(new TableUpdate('table:1', 7, 'race', 4, [], true), $last);
        self::assertSame(['ann'], $this->tables->state('table:1')->outcome?->winners);
        self::assertSame(0, $this->logs->read);
        self::assertSame(1, $this->logs->loads);
    }

    public function testAStateIsReadFromTheHeaderAndTheMovesMadeSinceTheCachedOne(): void
    {
        $this->tables->open('race', 'table:1', ['ann', 'bob'], [], self::SEED);
        $this->tables->play('table:1', new Move('race.step', 'ann', ['steps' => 2]));

        $earlier = $this->cache->get('table:1');
        $played = $this->tables->play('table:1', new Move('race.step', 'bob', ['steps' => 1]));
        $this->tables->play('table:1', new Move('race.taunt', 'ann'));
        self::assertInstanceOf(Snapshot::class, $earlier);

        // A cached state of an earlier revision of the same lineage is brought forward by the moves made since.
        $this->cache->forget('table:1');
        $this->cache->put($earlier);

        $setups = $this->race->setups;

        self::assertSame(3, $this->tables()->state('table:1')->sequence);
        self::assertSame([[2, 3]], $this->logs->windows);
        self::assertSame(2, $this->logs->read);
        self::assertSame($setups, $this->race->setups);
        self::assertSame(3, $this->cache->get('table:1')?->revision);

        // A cached state of another lineage — a move taken back since — is not: the log is replayed.
        $this->tables->undo('table:1', 'ann');
        $this->tables->undo('table:1', 'bob');
        $this->tables->play('table:1', new Move('race.step', 'bob', ['steps' => 3]));

        $this->cache->forget('table:1');
        $this->cache->put($earlier);

        $this->logs->windows = [];

        $state = $this->tables()->state('table:1');
        self::assertSame(['ann' => 2, 'bob' => 3], $state->data['positions']);
        self::assertSame([[1, 2]], $this->logs->windows);
        self::assertSame(6, $this->cache->get('table:1')?->revision);
        self::assertNotEquals($played, $state);

        // Without a cache, every read of a new request replays the log, once.
        $uncached = new Tables(new Games($this->race), $this->logs);
        $setups = $this->race->setups;
        self::assertSame(2, $uncached->state('table:1')->sequence);
        self::assertSame(2, $uncached->state('table:1')->sequence);
        self::assertSame($setups + 1, $this->race->setups);
        $uncached->reset();
        self::assertSame(2, $uncached->state('table:1')->sequence);
        self::assertSame($setups + 2, $this->race->setups);
    }

    public function testATableIsReadOnceAndItsStatesKeptWithinBounds(): void
    {
        $this->tables->open('race', 'table:1', ['ann', 'bob'], [], self::SEED);
        $this->tables->play('table:1', new Move('race.step', 'ann', ['steps' => 2]));

        $tables = new Tables(new Games($this->race), $this->logs);
        $this->logs->windows = [];

        $tables->state('table:1');
        $tables->view('table:1', 'ann');
        $tables->view('table:1');
        self::assertSame($this->race->definition()->game, $tables->header('table:1')->game);
        self::assertSame(hash('sha256', self::SEED), $tables->commitment('table:1'));
        self::assertSame([[1, 1]], $this->logs->windows);
        self::assertSame(0, $this->logs->loads);

        // Another process moves: the kept state is brought forward by that move alone.
        $this->tables->play('table:1', new Move('race.step', 'bob', ['steps' => 3]));
        self::assertSame(['ann' => 2, 'bob' => 3], $tables->view('table:1', 'ann')['positions']);
        self::assertSame([[1, 1], [2, 2]], $this->logs->windows);

        // The state read least recently is let go first.
        for ($table = 2; $table <= Tables::REMEMBERED + 1; ++$table) {
            $this->tables->open('race', 'table:'.$table, ['ann', 'bob'], [], self::SEED);
        }

        for ($table = 2; $table <= Tables::REMEMBERED; ++$table) {
            $tables->state('table:'.$table);
        }

        $setups = $this->race->setups;
        $tables->state('table:1');
        $tables->state('table:'.Tables::REMEMBERED);
        self::assertSame($setups, $this->race->setups);

        $tables->state('table:'.(Tables::REMEMBERED + 1));
        self::assertSame($setups + 1, $this->race->setups);
        $tables->state('table:1');
        self::assertSame($setups + 1, $this->race->setups);
        $tables->state('table:2');
        self::assertSame($setups + 2, $this->race->setups);
    }

    public function testAStaleStateOfTheSameNumberIsNeverServedNorWrittenBack(): void
    {
        $this->tables->open('race', 'table:1', ['ann', 'bob'], [], self::SEED);
        $this->tables->play('table:1', new Move('race.step', 'ann', ['steps' => 2]));

        $stale = $this->cache->get('table:1');
        $this->cache->forget('table:1');
        self::assertInstanceOf(Snapshot::class, $stale);

        // A reader replays the log while another process takes its move back and makes another of the same number.
        $writer = $this->tables();
        $this->logs->whileReading = static function () use ($writer): void {
            $writer->undo('table:1', 'ann');
            $writer->play('table:1', new Move('race.step', 'ann', ['steps' => 3]));
        };

        $state = $this->tables()->state('table:1');
        self::assertSame(['ann' => 3, 'bob' => 0], $state->data['positions']);
        self::assertSame(1, $state->sequence);
        self::assertSame(3, $this->cache->get('table:1')?->revision);
        self::assertSame($state, $this->cache->get('table:1')->state);

        // The stale state, of the same number of moves, is not written over the later one.
        $this->cache->put($stale);
        self::assertSame(3, $this->cache->get('table:1')?->revision);

        // Even when it lands in the cache, it is never served, and the state of the log takes its place.
        $this->cache->forget('table:1');
        $this->cache->put($stale);
        self::assertSame(['ann' => 3, 'bob' => 0], $this->tables()->state('table:1')->data['positions']);
        self::assertSame(3, $this->cache->get('table:1')?->revision);

        // A move made on the stale state is refused by the store.
        try {
            $this->logs->append($this->staleHeader(), new LoggedMove(2, Move::endTurn(null)));
            self::fail('A move made on a stale state was appended.');
        } catch (ConcurrentMove $concurrentMove) {
            self::assertSame('The log of the table "table:1" is at the revision 3, not the 1 expected: a move was taken back since.', $concurrentMove->getMessage());
        }
    }

    public function testALogThatKeepsChangingWhileItIsReadIsGivenUp(): void
    {
        $this->tables->open('race', 'table:1', ['ann', 'bob'], [], self::SEED);
        $this->tables->play('table:1', new Move('race.step', 'ann', ['steps' => 2]));

        $this->cache->forget('table:1');
        $writer = $this->tables();
        $this->logs->keep = true;
        $this->logs->whileReading = static function () use ($writer): void {
            $writer->play('table:1', new Move('race.taunt', 'ann'));
            $writer->undo('table:1', 'ann');
        };

        try {
            $this->tables()->state('table:1');
            self::fail('A log that kept changing was read.');
        } catch (ConcurrentMove $concurrentMove) {
            self::assertSame('The log of the table "table:1" changed during each of 3 reads.', $concurrentMove->getMessage());
        }

        // A log that changes once while it is read is read again.
        $this->logs->keep = false;
        $this->cache->forget('table:1');
        self::assertSame(1, $this->tables()->state('table:1')->sequence);
        self::assertSame(1 + 2 * (Tables::READS + 1), $this->tables->header('table:1')->revision);
    }

    public function testALogThatCannotBeReplayedStaysRefused(): void
    {
        $this->tables->open('race', 'table:1', ['ann', 'bob'], [], self::SEED);
        $this->tables->play('table:1', new Move('race.step', 'ann', ['steps' => 2]));

        $this->cache->forget('table:1');

        try {
            new Tables(new Games(new Race(stepsDraw: true)), $this->logs)->state('table:1');
            self::fail('A log that draws differently was replayed.');
        } catch (InvalidGame $invalidGame) {
            self::assertSame('The move 1 of the table "table:1" draws differently when it is replayed: the game is not pure, or its rules changed.', $invalidGame->getMessage());
            self::assertCount(1, $this->logs->windows);
        }

        // A failure while the log changes is a read to make again.
        $writer = $this->tables();
        $this->logs->whileReading = static function () use ($writer): void {
            $writer->undo('table:1', 'ann');
        };

        self::assertSame(0, new Tables(new Games(new Race(stepsDraw: true)), $this->logs)->state('table:1')->sequence);
    }

    public function testATakeBackOfALogThatChangesMeanwhileIsRefused(): void
    {
        $this->tables->open('race', 'table:1', ['ann', 'bob'], [], self::SEED);
        $this->tables->play('table:1', new Move('race.step', 'ann', ['steps' => 2]));
        $this->tables->play('table:1', new Move('race.step', 'bob', ['steps' => 2]));

        $writer = $this->tables();
        $this->logs->whileReading = static function () use ($writer): void {
            $writer->play('table:1', new Move('race.taunt', 'ann'));
        };

        try {
            $this->tables->undo('table:1', 'bob');
            self::fail('A move was taken back from a log that changed meanwhile.');
        } catch (ConcurrentMove $concurrentMove) {
            self::assertSame('The log of the table "table:1" changed during each of 1 reads.', $concurrentMove->getMessage());
        }

        self::assertSame(3, $this->tables->log('table:1')->sequence());
    }

    public function testACachedStateOfAnotherGameIsNotTrusted(): void
    {
        $this->tables->open('race', 'table:1', ['ann', 'bob'], [], self::SEED);
        $this->tables->open('roleplaying-scene', 'scene:1', ['gm', 'ann'], [], self::SEED);

        $scene = $this->tables->state('scene:1');
        $header = $this->tables->header('table:1');
        $this->cache->forget('table:1');
        $this->cache->put(new Snapshot(new GameState('table:1', 'roleplaying-scene', 0, $scene->seats, $scene->options, $scene->phase, $scene->turn), $header->revision, $header->commitment()));

        self::assertSame('race', $this->tables()->state('table:1')->game);
    }

    public function testAMoveSeenLateIsRefused(): void
    {
        $this->tables->open('race', 'table:1', ['ann', 'bob'], [], self::SEED);
        $read = $this->tables->header('table:1');
        $this->tables->play('table:1', new Move('race.step', 'ann', ['steps' => 2]), 0);

        try {
            $this->tables->play('table:1', new Move('race.step', 'bob', ['steps' => 2]), 0);
            self::fail('A late move was accepted.');
        } catch (ConcurrentMove $concurrentMove) {
            self::assertSame('The log of the table "table:1" holds 1 moves, not the 0 expected: another move came first.', $concurrentMove->getMessage());
        }

        self::assertSame(2, $this->tables->play('table:1', new Move('race.step', 'bob', ['steps' => 2]), 1)->sequence);

        foreach ([
            function () use ($read): void {
                $this->logs->append($read, new LoggedMove(1, Move::endTurn(null)));
            },
            function () use ($read): void {
                $this->logs->removeLast($read->appended());
            },
        ] as $write) {
            try {
                $write();
                self::fail('A stale write was accepted.');
            } catch (ConcurrentMove $exception) {
                self::assertStringContainsString('holds 2 moves', $exception->getMessage());
            }
        }
    }

    public function testAMoveIsTakenBackAndAViewIsRead(): void
    {
        $this->tables->open('race', 'table:1', ['ann', 'bob'], [], self::SEED);
        $this->tables->play('table:1', new Move('race.step', 'ann', ['steps' => 2]));

        $undone = $this->tables->undo('table:1', 'ann');

        self::assertSame(0, $undone->sequence);
        self::assertSame(0, $this->tables->log('table:1')->sequence());
        self::assertSame(2, $this->tables->header('table:1')->revision);
        self::assertSame($undone, $this->cache->get('table:1')?->state);
        self::assertCount(3, $this->publisher->updates);
        self::assertSame(['ann' => 0, 'bob' => 0], $this->tables->view('table:1', 'bob')['positions']);
        self::assertArrayHasKey('bob', (array) $this->tables->view('table:1', 'bob')['secrets']);
        self::assertSame([], $this->tables->view('table:1')['secrets']);

        try {
            $this->tables->undo('table:1');
            self::fail('A move was taken back from a table without one.');
        } catch (IllegalMove $illegalMove) {
            self::assertSame('The table has no move to take back.', $illegalMove->getMessage());
        }

        try {
            $this->logs->removeLast($this->tables->header('table:1'));
            self::fail('A move was removed from a log without one.');
        } catch (InvalidGame $invalidGame) {
            self::assertSame('The table "table:1" has no move to take back.', $invalidGame->getMessage());
        }
    }

    public function testAStateTheCacheHoldsAndTheLogReflectsIsNotReplayed(): void
    {
        $this->tables->open('race', 'table:1', ['ann', 'bob'], [], self::SEED);
        $this->tables->play('table:1', new Move('race.step', 'ann', ['steps' => 2]));

        $setups = $this->race->setups;
        $this->logs->windows = [];

        $tables = $this->tables();
        self::assertSame(1, $tables->state('table:1')->sequence);
        self::assertSame([], $this->logs->windows);
        self::assertSame($setups, $this->race->setups);

        // The state read is kept: the cache may forget it without the next read replaying anything.
        $this->cache->forget('table:1');
        self::assertSame(1, $tables->state('table:1')->sequence);
        self::assertSame([], $this->logs->windows);
        self::assertSame($setups, $this->race->setups);
    }

    public function testTheStateAMoveLeadsToIsKeptForTheNextRead(): void
    {
        $this->tables->open('race', 'table:1', ['ann', 'bob'], [], self::SEED);
        $this->tables->play('table:1', new Move('race.step', 'ann', ['steps' => 2]));

        $this->cache->forget('table:1');
        $this->logs->windows = [];
        $setups = $this->race->setups;

        self::assertSame(1, $this->tables->state('table:1')->sequence);
        self::assertSame([], $this->logs->windows);
        self::assertSame($setups, $this->race->setups);

        $this->tables->play('table:1', new Move('race.step', 'bob', ['steps' => 1]));
        $this->cache->forget('table:1');
        $this->logs->windows = [];

        self::assertSame(2, $this->tables->state('table:1')->sequence);
        self::assertSame([], $this->logs->windows);

        $this->tables->undo('table:1', 'bob');
        $this->cache->forget('table:1');
        $this->logs->windows = [];
        $setups = $this->race->setups;

        self::assertSame(1, $this->tables->state('table:1')->sequence);
        self::assertSame([], $this->logs->windows);
        self::assertSame($setups, $this->race->setups);
    }

    public function testAMoveTakenBackIsReplayedFromTheMovesBeforeIt(): void
    {
        $this->tables->open('race', 'table:1', ['ann', 'bob'], [], self::SEED);
        $this->tables->play('table:1', new Move('race.step', 'ann', ['steps' => 2]));
        $this->tables->play('table:1', new Move('race.step', 'bob', ['steps' => 1]));

        $this->logs->windows = [];

        $this->tables->undo('table:1', 'bob');

        // The last move is read to be checked, then the moves before it replayed.
        self::assertSame([[2, 2], [1, 1]], $this->logs->windows);
    }

    public function testNoMoveIsTakenBackOnceTheGameIsOver(): void
    {
        $this->tables->open('race', 'table:1', ['ann', 'bob'], ['target' => 10], self::SEED);

        for ($move = 0; $move < 6; ++$move) {
            $this->tables->play('table:1', new Move('race.step', $this->tables->state('table:1')->turn->toAct()[0], ['steps' => 3]));
        }

        $this->tables->play('table:1', new Move('race.taunt', 'bob'));
        self::assertSame(6, $this->tables->undo('table:1', 'bob')->sequence);
        self::assertTrue($this->tables->play('table:1', new Move('race.step', 'ann', ['steps' => 1]))->isOver());
        $updates = \count($this->publisher->updates);

        foreach (['ann', null] as $seat) {
            try {
                $this->tables->undo('table:1', $seat);
                self::fail('A move of a finished game was taken back.');
            } catch (GameOver $exception) {
                self::assertSame('The game at the table "table:1" is over: its seed may be shown, so no move is taken back.', $exception->getMessage());
            }
        }

        // Nor is any move made: the seed the finished log shows tells every draw, and none is drawn again.
        try {
            $this->tables->play('table:1', new Move('race.taunt', 'bob'));
            self::fail('A move was made at a finished table.');
        } catch (IllegalMove $illegalMove) {
            self::assertSame('The game at the table "table:1" is over.', $illegalMove->getMessage());
        }

        self::assertSame(self::SEED, $this->tables->finishedLog('table:1')->seed);
        self::assertSame(7, $this->tables->log('table:1')->sequence());
        self::assertSame(7, $this->tables->header('table:1')->revision - 2);
        self::assertCount($updates, $this->publisher->updates);
    }

    public function testTheLogIsShownOnlyOnceTheGameIsOverAndOnlyItsOwn(): void
    {
        $this->tables->open('race', 'table:1', ['ann', 'bob'], ['target' => 10], self::SEED);

        try {
            $this->tables->finishedLog('table:1');
            self::fail('The log of a game that goes on was shown.');
        } catch (GameInProgress $gameInProgress) {
            self::assertSame('The game at the table "table:1" is not over: its log, which holds the seed, stays secret until it is.', $gameInProgress->getMessage());
        }

        $this->finishRace();
        self::assertEquals($this->logs->inner->load('table:1'), $this->tables->finishedLog('table:1'));

        // The table is opened again under its identifier between the read of its state and the read of its log, with
        // another seed, or with the same seed and no move: neither log is shown for the finished one.
        $other = 'ffeeddccbbaa99887766554433221100ffeeddccbbaa99887766554433221100';

        foreach ([$other, self::SEED] as $seed) {
            $this->logs->beforeLoading = function () use ($seed): void {
                $this->logs->inner->reset();
                $this->tables()->open('race', 'table:1', ['ann', 'bob'], ['target' => 10], $seed);
            };

            try {
                $this->tables->finishedLog('table:1');
                self::fail('The log of a table opened again was shown.');
            } catch (ConcurrentMove $concurrentMove) {
                self::assertSame('The log of the table "table:1" changed during each of 1 reads.', $concurrentMove->getMessage());
            }

            $this->logs->inner->reset();
            $this->tables->open('race', 'table:1', ['ann', 'bob'], ['target' => 10], self::SEED);
            $this->finishRace();
        }
    }

    private function finishRace(): void
    {
        while (!$this->tables->state('table:1')->isOver()) {
            $this->tables->play('table:1', new Move('race.step', $this->tables->state('table:1')->turn->toAct()[0], ['steps' => 3]));
        }
    }

    public function testUnknownTablesAndGamesAreRefused(): void
    {
        foreach ([
            [fn (): mixed => $this->tables->open('chess', 'table:1', ['ann', 'bob']), 'No game is named "chess".'],
            [fn (): mixed => $this->tables->state('table:9'), 'No table has the identifier "table:9".'],
            [fn (): mixed => $this->tables->play('table:9', Move::endTurn(null)), 'No table has the identifier "table:9".'],
            [fn (): mixed => $this->tables->undo('table:9'), 'No table has the identifier "table:9".'],
            [fn (): mixed => $this->tables->log('table:9'), 'No table has the identifier "table:9".'],
            [fn (): mixed => $this->tables->commitment('table:9'), 'No table has the identifier "table:9".'],
        ] as [$call, $message]) {
            try {
                $call();
                self::fail('The call was accepted.');
            } catch (UnknownTable $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        self::assertSame(['race', 'roleplaying-scene'], new Games($this->race, new RoleplayingScene())->names());
        $this->tables->open('race', 'table:1', ['ann', 'bob'], [], self::SEED);

        foreach ([
            [fn (): mixed => $this->tables->open('race', 'table:1', ['ann', 'bob']), 'A table has the identifier "table:1".'],
            [function (): void {
                $this->logs->append($this->staleHeader('table:9'), new LoggedMove(1, Move::endTurn(null)));
            }, 'No table has the identifier "table:9".'],
            [static fn (): mixed => new Games(new Race(), new Race()), 'Two games are named "race".'],
        ] as [$call, $message]) {
            try {
                $call();
                self::fail('The call was accepted.');
            } catch (InvalidGame $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testTheMovesOfALogInMemoryAreReadByWindows(): void
    {
        $logs = new InMemoryLogStore();
        $tables = new Tables(new Games($this->race), $logs);
        $tables->open('race', 'table:1', ['ann', 'bob'], [], self::SEED);

        foreach (['ann', 'bob', 'ann'] as $seat) {
            $tables->play('table:1', new Move('race.step', $seat, ['steps' => 1]));
        }

        $numbers = static fn (iterable $moves): array => array_map(static fn (LoggedMove $move): int => $move->sequence, [...$moves]);
        self::assertSame([1, 2, 3], $numbers($logs->moves('table:1', 1, 3)));
        self::assertSame([2, 3], $numbers($logs->moves('table:1', 2, 9)));
        self::assertSame([1], $numbers($logs->moves('table:1', 0, 1)));
        self::assertSame([3], $numbers($logs->moves('table:1', 3, 3)));
        self::assertSame([], $numbers($logs->moves('table:1', 4, 3)));
        self::assertSame([], $numbers($logs->moves('table:9', 1, 3)));
        self::assertSame(3, $logs->header('table:1')?->revision);
        self::assertNull($logs->header('table:9'));

        $logs->reset();
        self::assertNull($logs->header('table:1'));
    }

    public function testTheNullCacheAndPublisherKeepNothing(): void
    {
        $cache = new NullStateCache();
        $state = $this->tables->open('race', 'table:1', ['ann', 'bob'], [], self::SEED);
        $cache->put(Snapshot::of($state, $this->tables->header('table:1')));
        $cache->forget('table:1');
        new NullTablePublisher()->publish(TableUpdate::of($state));

        self::assertNull($cache->get('table:1'));
    }

    private function tables(): Tables
    {
        return new Tables(new Games($this->race, new RoleplayingScene()), $this->logs, $this->cache, $this->publisher);
    }

    private function staleHeader(string $gameId = 'table:1'): LogHeader
    {
        return new LogHeader($gameId, 'race', '1', self::SEED, ['ann', 'bob'], [], 1, 1);
    }
}
