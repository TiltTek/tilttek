<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Exception\ConcurrentMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\Games;
use Tilttek\GameEngine\LoggedMove;
use Tilttek\GameEngine\LogHeader;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Tables;
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\DbalLogStore;
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\GameEngineTables;
use Tilttek\GameEngineBundle\Tests\Fixture\RollRace;

/**
 * The log store on Doctrine DBAL, against the database of each subclass: SQLite in memory, MySQL and PostgreSQL in
 * their containers.
 */
abstract class LogStoreTestCase extends TestCase
{
    protected const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    protected Connection $connection;

    protected DbalLogStore $store;

    protected function setUp(): void
    {
        $this->connection = $this->connection();

        foreach ([GameEngineTables::MOVES, GameEngineTables::TABLES] as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $schemaManager = $this->connection->createSchemaManager();
        $current = $schemaManager->introspectSchema();

        foreach ($this->connection->getDatabasePlatform()->getAlterSchemaSQL($schemaManager->createComparator()->compareSchemas($current, GameEngineTables::configure($current))) as $sql) {
            $this->connection->executeStatement($sql);
        }

        $this->store = new DbalLogStore($this->connection);
    }

    public function testALogIsCreatedAppendedAndReadBackAsItWasWritten(): void
    {
        $log = new GameLog('table:1', 'roll-race', '2', self::SEED, ['ann', 'bob'], ['target' => 12, 'fast' => true, 'track' => 'long']);
        $this->store->create($log);

        self::assertEquals($log, $this->store->load('table:1'));
        self::assertNull($this->store->load('table:2'));

        $first = new LoggedMove(1, new Move('roll.roll', 'ann', ['b' => 1, 'a' => ['z' => 1.0, 'y' => 'é/"x"'], 'list' => [3, 1, 2]]), true);
        $second = new LoggedMove(2, Move::endTurn(null));
        $this->store->append($log->header(), $first);
        $this->store->append($log->header()->appended(), $second);

        $read = $this->store->load('table:1');
        self::assertInstanceOf(GameLog::class, $read);
        self::assertEquals($log->with($first)->with($second), $read);
        self::assertSame(['b' => 1, 'a' => ['z' => 1.0, 'y' => 'é/"x"'], 'list' => [3, 1, 2]], $read->move(1)->move->payload);
        self::assertTrue($read->move(1)->drew);
        self::assertFalse($read->move(2)->drew);
        self::assertNull($read->move(2)->move->seat);
        self::assertSame([1, 0], array_map(static fn (mixed $drew): int => \is_bool($drew) || is_numeric($drew) ? (int) $drew : -1, $this->connection->fetchFirstColumn('SELECT drew FROM '.GameEngineTables::MOVES.' ORDER BY number')));

        $header = $this->store->header('table:1');
        self::assertEquals($log->with($first)->with($second)->header(), $header);
        $this->assertInstanceOf(LogHeader::class, $header);
        $this->store->removeLast($header);
        self::assertEquals($log->with($first), $this->store->load('table:1'));
        self::assertEquals($log->with($first)->header(3), $this->store->header('table:1'));
        $this->store->append($log->with($first)->header(3), $second);
        self::assertEquals($log->with($first)->with($second), $this->store->load('table:1'));
        self::assertEquals($log->with($first)->with($second)->header(4), $this->store->header('table:1'));
        self::assertNull($this->store->header('table:2'));
    }

    public function testTheMovesAreReadWindowByWindow(): void
    {
        $count = DbalLogStore::WINDOW * 2 + 3;
        $moves = [];

        for ($number = 1; $number <= $count; ++$number) {
            $moves[] = new LoggedMove($number, new Move('roll.wait', 0 === $number % 2 ? 'bob' : 'ann', ['n' => $number]), 0 === $number % 7);
        }

        $log = new GameLog('table:1', 'roll-race', '1', self::SEED, ['ann', 'bob'], [], $moves);
        $this->store->create($log);

        self::assertEquals($moves, [...$this->store->moves('table:1', 1, $count)]);
        self::assertEquals(\array_slice($moves, DbalLogStore::WINDOW - 2, 5), [...$this->store->moves('table:1', DbalLogStore::WINDOW - 1, DbalLogStore::WINDOW + 3)]);
        self::assertEquals(\array_slice($moves, $count - 2), [...$this->store->moves('table:1', $count - 1, $count + 50)]);
        self::assertEquals([$moves[0]], [...$this->store->moves('table:1', 0, 1)]);
        self::assertSame([], [...$this->store->moves('table:1', 5, 4)]);
        self::assertSame([], [...$this->store->moves('table:9', 1, 4)]);
        self::assertEquals($log, $this->store->load('table:1'));

        // A gap — moves removed while they are read — ends the read: the caller reads the header again.
        $this->connection->executeStatement('DELETE FROM '.GameEngineTables::MOVES.' WHERE game_id = ? AND number = ?', ['table:1', DbalLogStore::WINDOW + 1]);
        self::assertCount(DbalLogStore::WINDOW, [...$this->store->moves('table:1', 1, $count)]);
        $this->connection->executeStatement('DELETE FROM '.GameEngineTables::MOVES.' WHERE game_id = ? AND number = ?', ['table:1', 3]);
        self::assertCount(2, [...$this->store->moves('table:1', 1, $count)]);
        $this->connection->executeStatement('DELETE FROM '.GameEngineTables::MOVES.' WHERE game_id = ? AND number = ?', ['table:1', 1]);
        self::assertSame([], [...$this->store->moves('table:1', 1, $count)]);

        try {
            $this->store->load('table:1');
            self::fail('A log with a gap was read.');
        } catch (ConcurrentMove $concurrentMove) {
            self::assertSame('The log of the table "table:1" changed during each of 3 reads.', $concurrentMove->getMessage());
        }
    }

    public function testAPayloadIsReadBackWithItsKeysAsTexts(): void
    {
        $this->store->create(new GameLog('table:1', 'roll-race', '1', self::SEED, ['ann', 'bob'], [], [new LoggedMove(1, new Move('roll.wait', 'ann'))]));
        $this->connection->executeStatement('UPDATE '.GameEngineTables::MOVES.' SET payload = ? WHERE game_id = ?', ['{"7": 1}', 'table:1']);
        self::assertSame('{"7":1}', json_encode(array_first([...$this->store->moves('table:1', 1, 1)])?->move->payload));
        self::assertSame(1, $this->store->header('table:1')?->sequence);
    }

    public function testALogWithMovesIsCreatedWithThem(): void
    {
        $log = new GameLog('table:1', 'roll-race', '1', self::SEED, ['ann', 'bob'], [], [new LoggedMove(1, new Move('roll.wait', 'ann')), new LoggedMove(2, new Move('roll.wait', 'bob'))]);
        $this->store->create($log);

        self::assertEquals($log, $this->store->load('table:1'));
    }

    public function testTwoMovesOfOneNumberNeverBothLand(): void
    {
        $log = new GameLog('table:1', 'roll-race', '1', self::SEED, ['ann', 'bob']);
        $this->store->create($log);
        $opened = $log->header();
        $this->store->append($opened, new LoggedMove(1, new Move('roll.wait', 'ann')));
        $one = $opened->appended();
        $this->store->removeLast($one);
        $this->store->append($opened->appended()->withoutLast(), new LoggedMove(1, new Move('roll.wait', 'ann')));

        $replaced = $one->withoutLast()->appended();
        $other = new GameLog('table:9', 'roll-race', '1', self::SEED, ['ann', 'bob'])->header();

        foreach ([
            [fn () => $this->store->append($opened, new LoggedMove(1, new Move('roll.wait', 'bob'))), ConcurrentMove::class, 'The log of the table "table:1" holds 1 moves, not the 0 expected: another move came first.'],
            [fn () => $this->store->append($one->appended(), new LoggedMove(3, new Move('roll.wait', 'bob'))), ConcurrentMove::class, 'The log of the table "table:1" holds 1 moves, not the 2 expected: another move came first.'],
            [fn () => $this->store->removeLast($one->appended()), ConcurrentMove::class, 'The log of the table "table:1" holds 1 moves, not the 2 expected: another move came first.'],
            [fn () => $this->store->append($one, new LoggedMove(2, new Move('roll.wait', 'bob'))), ConcurrentMove::class, 'The log of the table "table:1" is at the revision 3, not the 1 expected: a move was taken back since.'],
            [fn () => $this->store->removeLast($one), ConcurrentMove::class, 'The log of the table "table:1" is at the revision 3, not the 1 expected: a move was taken back since.'],
            [fn () => $this->store->append($replaced, new LoggedMove(3, new Move('roll.wait', 'bob'))), InvalidGame::class, 'The move 3 cannot follow the 1 moves of the table "table:1".'],
            [fn () => $this->store->append(new LogHeader('table:1', 'roll-race', '1', self::SEED, ['ann'], [], GameLog::MAX_MOVES, GameLog::MAX_MOVES), new LoggedMove(GameLog::MAX_MOVES + 1, new Move('roll.wait', 'bob'))), InvalidGame::class, 'The log of the table "table:1" holds more than 100000 moves.'],
            [fn () => $this->store->append($other, new LoggedMove(1, new Move('roll.wait', 'bob'))), InvalidGame::class, 'No table has the identifier "table:9".'],
            [fn () => $this->store->removeLast($other->appended()), InvalidGame::class, 'No table has the identifier "table:9".'],
            [fn () => $this->store->removeLast($opened), InvalidGame::class, 'The table "table:1" has no move to take back.'],
            [fn () => $this->store->create(new GameLog('table:1', 'roll-race', '1', self::SEED, ['cy'])), InvalidGame::class, 'A table has the identifier "table:1".'],
        ] as [$act, $class, $message]) {
            try {
                $act();
                self::fail(\sprintf('"%s" was accepted.', $message));
            } catch (ConcurrentMove|InvalidGame $exception) {
                self::assertInstanceOf($class, $exception);
                self::assertSame($message, $exception->getMessage());
            }
        }

        $log = $this->store->load('table:1');
        self::assertInstanceOf(GameLog::class, $log);
        self::assertSame(1, $log->sequence());
        self::assertSame('ann', $log->move(1)->move->seat);
        self::assertSame(['ann', 'bob'], $log->seats);
        self::assertSame(3, $this->store->header('table:1')?->revision);
        $this->store->append($replaced, new LoggedMove(2, new Move('roll.wait', 'bob')));
        self::assertSame(4, $this->store->header('table:1')?->revision);
    }

    public function testATableIsDeletedWithItsMoves(): void
    {
        $this->store->create(new GameLog('table:1', 'roll-race', '1', self::SEED, ['ann', 'bob'], [], [new LoggedMove(1, new Move('roll.wait', 'ann'))]));
        $this->store->create(new GameLog('table:2', 'roll-race', '1', self::SEED, ['ann', 'bob'], [], [new LoggedMove(1, new Move('roll.wait', 'ann'))]));

        self::assertTrue($this->store->delete('table:1'));
        self::assertFalse($this->store->delete('table:1'));
        self::assertNull($this->store->load('table:1'));
        self::assertEquals(0, $this->connection->fetchOne('SELECT COUNT(*) FROM '.GameEngineTables::MOVES." WHERE game_id = 'table:1'"));
        self::assertSame(1, $this->store->load('table:2')?->sequence());
    }

    public function testAMoveBeyondTheCountOfItsTableIsNotRead(): void
    {
        $this->store->create(new GameLog('table:1', 'roll-race', '1', self::SEED, ['ann', 'bob']));
        $this->connection->insert(GameEngineTables::MOVES, ['game_id' => 'table:1', 'number' => 1, 'type' => 'roll.wait', 'seat' => 'ann', 'payload' => '[]', 'drew' => 0]);

        self::assertSame(0, $this->store->load('table:1')?->sequence());
    }

    public function testARevisionIsReadAsAWholeNumberAndChecked(): void
    {
        $this->store->create(new GameLog('table:1', 'roll-race', '1', self::SEED, ['ann', 'bob']));
        $created = $this->store->header('table:1');
        self::assertSame(0, $created?->revision);

        $this->connection->executeStatement('UPDATE '.GameEngineTables::TABLES." SET revision = 12345678901 WHERE game_id = 'table:1'");
        $large = $this->store->header('table:1');
        self::assertSame(12345678901, $large?->revision);

        $this->connection->executeStatement('UPDATE '.GameEngineTables::TABLES." SET revision = -1 WHERE game_id = 'table:1'");

        try {
            $this->store->header('table:1');
            self::fail('A revision lower than the moves was read.');
        } catch (InvalidGame $invalidGame) {
            self::assertSame('The log of the table "table:1" is at the revision -1, fewer than its 0 moves.', $invalidGame->getMessage());
        }
    }

    public function testAColumnThatIsNotJsonIsRefused(): void
    {
        $this->store->create(new GameLog('table:1', 'roll-race', '1', self::SEED, ['ann', 'bob']));

        foreach ([['seats' => 'not json', 'message' => 'The log of the table "table:1" holds a column that is not JSON.'], ['seats' => '"ann"', 'message' => 'The log of the table "table:1" holds a column that is not a JSON array or object.']] as $case) {
            $this->connection->update(GameEngineTables::TABLES, ['seats' => $case['seats']], ['game_id' => 'table:1']);

            try {
                $this->store->load('table:1');
                self::fail('A column that is not JSON was read.');
            } catch (InvalidGame $exception) {
                self::assertSame($case['message'], $exception->getMessage());
            }
        }
    }

    public function testTablesPlayOnTheStore(): void
    {
        $tables = new Tables(new Games(new RollRace()), $this->store);
        $tables->open('roll-race', 'table:1', ['ann', 'bob'], ['target' => 6], self::SEED);

        $tables->play('table:1', new Move('roll.roll', 'ann'), 0);

        $state = $tables->play('table:1', new Move('roll.wait', 'bob'), 1);

        self::assertSame(2, $state->sequence);
        self::assertEquals($state, $tables->state('table:1'));
        self::assertEquals($tables->state('table:1'), new Tables(new Games(new RollRace()), new DbalLogStore($this->connection))->state('table:1'));

        $this->expectException(ConcurrentMove::class);

        $tables->play('table:1', new Move('roll.roll', 'ann'), 1);
    }

    abstract protected function connection(): Connection;
}
