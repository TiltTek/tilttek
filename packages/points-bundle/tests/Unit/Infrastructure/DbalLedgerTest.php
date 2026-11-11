<?php

declare(strict_types=1);

namespace Tilttek\PointsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use LogicException;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Points\Account;
use Tilttek\Points\Exception\InsufficientPoints;
use Tilttek\Points\Exception\InvalidPoints;
use Tilttek\Points\Ledger\Ledger;
use Tilttek\Points\MovementKey;
use Tilttek\Points\MovementKind;
use Tilttek\PointsBundle\Infrastructure\Doctrine\DbalLedger;
use Tilttek\PointsBundle\Infrastructure\Doctrine\PointsTables;
use UnexpectedValueException;

/**
 * The ledger on SQLite in memory, its tables created from `PointsTables`.
 */
#[CoversClass(DbalLedger::class)]
#[CoversClass(PointsTables::class)]
final class DbalLedgerTest extends LedgerTestCase
{
    private Connection $connection;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = Database::connection();
    }

    public function testTheTables(): void
    {
        $schema = $this->connection->createSchemaManager()->introspectSchema();
        $movements = $schema->getTable(PointsTables::MOVEMENTS);

        self::assertSame(['position'], $movements->getPrimaryKey()?->getColumns());
        self::assertTrue($movements->getColumn('position')->getAutoincrement());
        self::assertSame(
            ['position' => [null, true], 'movement_key' => [400, true], 'kind' => [16, true], 'currency' => [32, true], 'debit' => [340, true], 'credit' => [340, true], 'amount' => [null, true], 'occurred_at' => [null, true], 'recorded_at' => [null, true], 'cause' => [255, false], 'reverses' => [400, false]],
            array_merge(...array_map(static fn (Column $column): array => [$column->getName() => [$column->getLength(), $column->getNotnull()]], $movements->getColumns())),
        );
        self::assertSame(['movement_key'], $movements->getIndex('tilttek_points_movement_key')->getColumns());
        self::assertTrue($movements->getIndex('tilttek_points_movement_key')->isUnique());
        self::assertSame(['debit', 'currency'], $movements->getIndex('tilttek_points_movement_debit')->getColumns());
        self::assertSame(['credit', 'currency'], $movements->getIndex('tilttek_points_movement_credit')->getColumns());
        self::assertSame(['cause'], $movements->getIndex('tilttek_points_movement_cause')->getColumns());
        self::assertSame(['kind', 'occurred_at'], $movements->getIndex('tilttek_points_movement_kind')->getColumns());

        $locks = $schema->getTable(PointsTables::LOCKS);
        self::assertSame(['account', 'currency'], $locks->getPrimaryKey()?->getColumns());
        self::assertSame(
            ['account' => [340, true], 'currency' => [32, true], 'payments' => [null, true]],
            array_merge(...array_map(static fn (Column $column): array => [$column->getName() => [$column->getLength(), $column->getNotnull()]], $locks->getColumns())),
        );
    }

    public function testTablesAlreadyThereAreLeftAsTheyAre(): void
    {
        $schema = PointsTables::configure(new Schema());
        $again = PointsTables::configure($schema);

        self::assertCount(2, $again->getTables());
        self::assertSame($schema->getTable(PointsTables::MOVEMENTS)->getColumns(), $again->getTable(PointsTables::MOVEMENTS)->getColumns());
        self::assertSame($schema->getTable(PointsTables::LOCKS)->getColumns(), $again->getTable(PointsTables::LOCKS)->getColumns());
    }

    public function testTheInsertionsWriteNothingWhenTheKeyIsThere(): void
    {
        $into = 'INTO tilttek_points_movement (movement_key, kind, currency, debit, credit, amount, occurred_at, recorded_at, cause, reverses) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        self::assertSame('INSERT '.$into.' ON CONFLICT (movement_key) DO NOTHING', DbalLedger::insertion(new PostgreSQLPlatform()));
        self::assertSame('INSERT IGNORE '.$into, DbalLedger::insertion(new MySQLPlatform()));
        self::assertSame('INSERT IGNORE '.$into, DbalLedger::insertion(new MariaDBPlatform()));
        self::assertSame('INSERT OR IGNORE '.$into, DbalLedger::insertion(new SQLitePlatform()));
        self::assertSame('INSERT '.$into, DbalLedger::insertion(new SQLServerPlatform()));
        self::assertSame('INSERT '.$into, DbalLedger::insertion(new OraclePlatform()));

        $lock = 'INTO tilttek_points_lock (account, currency, payments) VALUES (?, ?, 0)';
        self::assertSame('INSERT '.$lock.' ON CONFLICT (account, currency) DO NOTHING', DbalLedger::lockInsertion(new PostgreSQLPlatform()));
        self::assertSame('INSERT IGNORE '.$lock, DbalLedger::lockInsertion(new MySQLPlatform()));
        self::assertSame('INSERT OR IGNORE '.$lock, DbalLedger::lockInsertion(new SQLitePlatform()));
        self::assertSame('INSERT '.$lock, DbalLedger::lockInsertion(new SQLServerPlatform()));
    }

    public function testAPaymentLocksItsAccountsRow(): void
    {
        $ledger = $this->ledger();
        $ledger->append(self::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 10), self::at());
        $ledger->appendCovered(self::movement('b', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 4), self::at());
        $ledger->appendCovered(self::movement('c', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 4), self::at());

        try {
            $ledger->appendCovered(self::movement('d', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 4), self::at());
            self::fail('Insufficient points were expected.');
        } catch (InsufficientPoints) {
            self::assertFalse($this->connection->isTransactionActive());
        }

        self::assertSame([['member:member:1', 'points', 2]], $this->connection->fetchAllNumeric('SELECT account, currency, payments FROM tilttek_points_lock'));
        $ledger->appendCovered(self::movement('b', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 4), self::at());
        self::assertSame(3, $this->connection->fetchOne('SELECT payments FROM tilttek_points_lock'));
        $ledger->eraseMember(ResourceReference::of('member', '1'));
        self::assertSame([], $this->connection->fetchAllNumeric('SELECT account, currency FROM tilttek_points_lock'));
    }

    public function testAPaymentWhoseLockRowIsMissing(): void
    {
        $this->connection->executeStatement('CREATE TRIGGER unlocked BEFORE UPDATE ON tilttek_points_lock BEGIN SELECT RAISE(IGNORE); END');
        $ledger = $this->ledger();
        $ledger->append(self::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 10), self::at());

        try {
            $ledger->appendCovered(self::movement('b', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 4), self::at());
            self::fail('A missing lock row was expected to stop the payment.');
        } catch (LogicException $logicException) {
            self::assertSame('The lock row of "member:member:1" in points is neither written nor found.', $logicException->getMessage());
        }

        self::assertNull($ledger->find(MovementKey::fromString('b')));
    }

    public function testAWriteThatLosesARaceIsReadBack(): void
    {
        // Another process writes the same key between the look-up and the insertion: the trigger writes the row, then
        // drops the insertion that fired it, as the insertion that ignores a duplicate would.
        $this->connection->executeStatement("CREATE TRIGGER race BEFORE INSERT ON tilttek_points_movement WHEN NEW.movement_key = 'a' AND NOT EXISTS (SELECT 1 FROM tilttek_points_movement WHERE movement_key = 'a') BEGIN INSERT INTO tilttek_points_movement (movement_key, kind, currency, debit, credit, amount, occurred_at, recorded_at, cause, reverses) VALUES ('a', 'granted', 'points', 'issuance:manual', 'member:member:1', 10, '2026-11-01 10:00:00', '2026-11-01 09:00:00', NULL, NULL); SELECT RAISE(IGNORE); END");

        $recorded = $this->ledger()->append(self::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 10), self::at());
        self::assertFalse($recorded->new);
        self::assertSame('2026-11-01 09:00:00', $recorded->entry->at->format('Y-m-d H:i:s'));
    }

    public function testAMovementNeitherWrittenNorFound(): void
    {
        $this->connection->executeStatement('CREATE TRIGGER lost BEFORE INSERT ON tilttek_points_movement BEGIN SELECT RAISE(IGNORE); END');

        $this->expectExceptionObject(new LogicException('The movement "a" is neither written nor found.'));
        $this->ledger()->append(self::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 10), self::at());
    }

    public function testDatesAreWrittenInUtc(): void
    {
        $ledger = $this->ledger();
        $recorded = $ledger->append(self::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 10), new DateTimeImmutable('2026-11-07 07:00:00-05:00'));
        $ledger->append(self::movement('b', MovementKind::Earned, 'issuance:games', 'member:member:1', 10, '2026-11-07 14:30:00'), self::at());

        self::assertSame('2026-11-07T12:00:00+00:00', $recorded->entry->at->format(\DATE_ATOM));
        self::assertSame('2026-11-07 12:00:00', $this->connection->fetchOne('SELECT recorded_at FROM tilttek_points_movement WHERE position = 1'));
        self::assertSame(10, $ledger->net(Account::fromString('member:member:1'), Account::issuance('games'), 'points', new DateTimeImmutable('2026-11-07 09:30:00-05:00'), new DateTimeImmutable('2026-11-07 09:30:01-05:00')));
        self::assertSame(0, $ledger->net(Account::fromString('member:member:1'), Account::issuance('games'), 'points', new DateTimeImmutable('2026-11-07 09:30:01-05:00')));
        self::assertSame(0, $ledger->net(Account::fromString('member:member:1'), Account::issuance('games'), 'points', null, new DateTimeImmutable('2026-11-07 09:30:00-05:00')));
        self::assertSame(['member:member:1'], array_map(static fn (Account $account): string => $account->toString(), $ledger->membersCreditedBefore('points', new DateTimeImmutable('2026-11-07 09:30:00-05:00'))));
        self::assertSame(1, $ledger->forgetDerived(new DateTimeImmutable('2026-11-07 09:30:00-05:00')));
        self::assertNull($ledger->find(MovementKey::fromString('b')));
    }

    public function testMembersAreTheAccountsOfTheMemberType(): void
    {
        $ledger = $this->ledger();
        $ledger->append(self::movement('a', MovementKind::Spent, 'issuance:manual', 'redemption:member:1', 1), self::at());

        self::assertSame([], $ledger->membersCreditedBefore('points', self::at()));
    }

    public function testARowThatDoesNotReadIsRefused(): void
    {
        // Columns without a type keep what is written as it is written: SQLite would turn "12\n" into 12 in a BIGINT.
        $this->connection->executeStatement('DROP TABLE tilttek_points_movement');
        $this->connection->executeStatement('CREATE TABLE tilttek_points_movement (position INTEGER PRIMARY KEY AUTOINCREMENT, movement_key, kind, currency, debit, credit, amount, occurred_at, recorded_at, cause, reverses)');
        $this->connection->executeStatement("INSERT INTO tilttek_points_movement (movement_key, kind, currency, debit, credit, amount, occurred_at, recorded_at) VALUES ('a', 'granted', 'points', 'issuance:manual', 'member:member:1', '17', '2026-11-01 10:00:00', '2026-11-01 10:00:00')");
        self::assertSame(17, $this->ledger()->find(MovementKey::fromString('a'))?->movement->amount);

        foreach (["'a', 'granted', 'points', 'issuance:manual', 'member:member:1', 'ten', '2026-11-01 10:00:00', '2026-11-01 10:00:00'" => 'An integer column holds string.', "'a', 'granted', 'points', 'issuance:manual', 'member:member:1', '12abc', '2026-11-01 10:00:00', '2026-11-01 10:00:00'" => 'An integer column holds string.', "'a', 'granted', 'points', 'issuance:manual', 'member:member:1', 'x12', '2026-11-01 10:00:00', '2026-11-01 10:00:00'" => 'An integer column holds string.', "'a', 'granted', 'points', 'issuance:manual', 'member:member:1', '12'||char(10), '2026-11-01 10:00:00', '2026-11-01 10:00:00'" => 'An integer column holds string.', "'a', 'granted', 'points', 'issuance:manual', 'member:member:1', 1.5, '2026-11-01 10:00:00', '2026-11-01 10:00:00'" => 'An integer column holds float.', "'a', 'granted', 'points', 'issuance:manual', 'member:member:1', 1, 20261101, '2026-11-01 10:00:00'" => 'A text column holds int.'] as $values => $message) {
            $this->connection->executeStatement('DELETE FROM tilttek_points_movement');
            $this->connection->executeStatement('INSERT INTO tilttek_points_movement (movement_key, kind, currency, debit, credit, amount, occurred_at, recorded_at) VALUES ('.$values.')');

            try {
                $this->ledger()->find(MovementKey::fromString('a'));
                self::fail('The row '.$values.' was read.');
            } catch (UnexpectedValueException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        $this->connection->executeStatement('DELETE FROM tilttek_points_movement');
        $this->connection->executeStatement("INSERT INTO tilttek_points_movement (movement_key, kind, currency, debit, credit, amount, occurred_at, recorded_at) VALUES ('a', 'granted', 'points', 'issuance:manual', 'member:member:1', '-12', '2026-11-01 10:00:00', '2026-11-01 10:00:00')");

        try {
            $this->ledger()->find(MovementKey::fromString('a'));
            self::fail('A negative amount was read.');
        } catch (InvalidPoints $invalidPoints) {
            self::assertSame('A movement moves 1 to 1000000000000000 points; this one moves -12.', $invalidPoints->getMessage());
        }
    }

    /**
     * A payment on MySQL, which a stub of the connection stands in for: started on its own, it reads committed rows;
     * started inside a transaction the caller opened, whose snapshot may predate the lock, it reads the movement and the
     * balance through shared locks.
     */
    public function testAPaymentOnMySqlReadsCommittedRowsOnItsOwnAndThroughLocksInsideATransaction(): void
    {
        $insertion = DbalLedger::lockInsertion(new MySQLPlatform());
        $update = 'UPDATE tilttek_points_lock SET payments = payments + 1 WHERE account = ? AND currency = ?';
        $read = 'SELECT position, movement_key, kind, currency, debit, credit, amount, occurred_at, recorded_at, cause, reverses FROM tilttek_points_movement WHERE movement_key = ?';
        $balance = 'SELECT COALESCE(SUM(CASE WHEN credit = ? THEN amount ELSE 0 - amount END), 0) FROM tilttek_points_movement WHERE currency = ? AND (credit = ? OR debit = ?)';

        self::assertSame(['SET TRANSACTION ISOLATION LEVEL READ COMMITTED', $insertion, $update, $read, $balance], $this->mySqlPayment(false));
        self::assertSame([$insertion, $update, $read.' LOCK IN SHARE MODE', $balance.' LOCK IN SHARE MODE'], $this->mySqlPayment(true));
    }

    #[Override]
    protected function ledger(): Ledger
    {
        return new DbalLedger($this->connection);
    }

    /**
     * The statements a payment of 100 points from an empty account sends to a connection that says it is MySQL.
     *
     * @return list<string>
     */
    private function mySqlPayment(bool $inTransaction): array
    {
        $statements = [];
        $connection = self::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('isTransactionActive')->willReturn($inTransaction);
        $connection->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work($connection));
        $connection->method('executeStatement')->willReturnCallback(static function (string $sql) use (&$statements): int {
            $statements[] = $sql;

            return 1;
        });
        $connection->method('fetchAssociative')->willReturnCallback(static function (string $sql) use (&$statements): false {
            $statements[] = $sql;

            return false;
        });
        $connection->method('fetchOne')->willReturnCallback(static function (string $sql) use (&$statements): int {
            $statements[] = $sql;

            return 0;
        });

        try {
            new DbalLedger($connection)->appendCovered(self::movement('spend', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 100), self::at());
            self::fail('Insufficient points were expected.');
        } catch (InsufficientPoints $insufficientPoints) {
            self::assertSame('The account "member:member:1" holds 0 points; 100 cannot be paid from it.', $insufficientPoints->getMessage());
        }

        return $statements;
    }
}
