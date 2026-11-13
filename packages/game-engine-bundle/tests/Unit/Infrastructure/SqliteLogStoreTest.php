<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\GameEngine\Exception\ConcurrentMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\LoggedMove;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\DbalLogStore;
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\GameEngineTables;
use Tilttek\GameEngineBundle\Tests\Fixture\ProbeConnection;

/**
 * SQLite in memory, through `pdo_sqlite`.
 */
#[CoversClass(DbalLogStore::class)]
#[CoversClass(GameEngineTables::class)]
final class SqliteLogStoreTest extends LogStoreTestCase
{
    #[Override]
    protected function connection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => ProbeConnection::class]);
    }

    public function testAWholeLogIsReadAtMostItsNumberOfTimes(): void
    {
        $probe = $this->probe();
        $this->store->create(new GameLog('table:1', 'roll-race', '1', self::SEED, ['ann', 'bob'], [], [new LoggedMove(1, new Move('roll.wait', 'ann'))]));
        $this->connection->executeStatement('DELETE FROM '.GameEngineTables::MOVES.' WHERE game_id = ?', ['table:1']);
        $probe->queries = [];

        try {
            $this->store->load('table:1');
            self::fail('A log with a gap was read.');
        } catch (ConcurrentMove) {
            self::assertCount(3, $probe->reads('SELECT game, version'));
            self::assertSame([['table:1', 1, 1], ['table:1', 1, 1], ['table:1', 1, 1]], $probe->reads('SELECT number'));
        }
    }

    public function testATableForgottenWhileItsMovesAreReadIsNoLongerThere(): void
    {
        $probe = $this->probe();
        $this->store->create(new GameLog('table:1', 'roll-race', '1', self::SEED, ['ann', 'bob'], [], [new LoggedMove(1, new Move('roll.wait', 'ann'))]));
        $probe->before = function (string $sql): void {
            if (str_starts_with($sql, 'SELECT number')) {
                $this->connection->executeStatement('DELETE FROM '.GameEngineTables::TABLES.' WHERE game_id = ?', ['table:1']);
            }
        };

        self::assertNull($this->store->load('table:1'));
    }

    public function testTheMovesAreAskedForByWindowsOfExactlyTheirSize(): void
    {
        $probe = $this->probe();
        $count = DbalLogStore::WINDOW * 2 + 3;
        $moves = [];

        for ($number = 1; $number <= $count; ++$number) {
            $moves[] = new LoggedMove($number, new Move('roll.wait', 'ann'));
        }

        $this->store->create(new GameLog('table:1', 'roll-race', '1', self::SEED, ['ann', 'bob'], [], $moves));
        $probe->queries = [];

        self::assertCount($count, [...$this->store->moves('table:1', 1, $count)]);
        self::assertSame([
            ['table:1', 1, DbalLogStore::WINDOW],
            ['table:1', DbalLogStore::WINDOW + 1, DbalLogStore::WINDOW * 2],
            ['table:1', DbalLogStore::WINDOW * 2 + 1, $count],
        ], $probe->reads('SELECT number'));

        $probe->queries = [];
        self::assertCount(1, [...$this->store->moves('table:1', 0, 1)]);
        self::assertSame([['table:1', 1, 1]], $probe->reads('SELECT number'));
    }

    public function testAMoveWhoseTypeOrSeatIsNotATextIsRefused(): void
    {
        $probe = $this->probe();
        $this->store->create(new GameLog('table:1', 'roll-race', '1', self::SEED, ['ann', 'bob'], [], [new LoggedMove(1, new Move('roll.wait', 'ann'))]));

        foreach ([['type', 5], ['seat', 5], ['type', null]] as [$column, $value]) {
            $probe->rewrite = static fn (string $sql, array $row): array => str_starts_with($sql, 'SELECT number') ? [$column => $value] + $row : $row;

            try {
                [...$this->store->moves('table:1', 1, 1)];
                self::fail('A move that is not made of texts was read.');
            } catch (InvalidGame $invalidGame) {
                self::assertSame('The move 1 of the log of the table "table:1" has a type or a seat that is not a text.', $invalidGame->getMessage());
            }
        }

        $probe->rewrite = static fn (string $sql, array $row): array => str_starts_with($sql, 'SELECT number') ? ['seat' => null] + $row : $row;
        self::assertNull([...$this->store->moves('table:1', 1, 1)][0]->move->seat);
    }

    public function testACountThatComesAsATextIsReadWhenItIsWholeAndRefusedOtherwise(): void
    {
        $probe = $this->probe();
        $this->store->create(new GameLog('table:1', 'roll-race', '1', self::SEED, ['ann', 'bob'], [], [new LoggedMove(1, new Move('roll.wait', 'ann'))]));

        $probe->rewrite = static fn (string $sql, array $row): array => str_starts_with($sql, 'SELECT game, version') ? ['moves' => '1', 'revision' => '1234567890123456'] + $row : $row;
        $header = $this->store->header('table:1');
        self::assertSame(1, $header?->sequence);
        self::assertSame(1234567890123456, $header->revision);

        foreach (['abc', '12x', '1.5', '', '1234567890123456789', ' 1', "12\n"] as $text) {
            $probe->rewrite = static fn (string $sql, array $row): array => str_starts_with($sql, 'SELECT game, version') ? ['moves' => $text] + $row : $row;

            try {
                $this->store->header('table:1');
                self::fail(\sprintf('The count "%s" was read.', $text));
            } catch (InvalidGame $invalidGame) {
                self::assertSame('The log of the table "table:1" holds a count that is not a whole number.', $invalidGame->getMessage());
            }
        }
    }

    private function probe(): ProbeConnection
    {
        self::assertInstanceOf(ProbeConnection::class, $this->connection);

        return $this->connection;
    }
}
