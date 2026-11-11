<?php

declare(strict_types=1);

namespace Tilttek\PointsBundle\Tests\Integration;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tilttek\Points\Account;
use Tilttek\Points\Exception\InsufficientPoints;
use Tilttek\Points\Ledger\Ledger;
use Tilttek\Points\MovementKey;
use Tilttek\Points\MovementKind;
use Tilttek\PointsBundle\Infrastructure\Doctrine\DbalLedger;
use Tilttek\PointsBundle\Infrastructure\Doctrine\PointsTables;
use Tilttek\PointsBundle\Tests\Integration\Fixture\Meanwhile;
use Tilttek\PointsBundle\Tests\Unit\Infrastructure\LedgerTestCase;

/**
 * The ledger on MySQL 8 with InnoDB, at its default isolation, REPEATABLE READ: the contract of every ledger, and two
 * payments from the same account that meet, the first one committed by `Meanwhile` while the second is under way.
 *
 *     PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test' vendor/bin/phpunit packages/tilttek-points-bundle/tests/Integration
 */
#[CoversClass(DbalLedger::class)]
#[CoversClass(PointsTables::class)]
#[Group('mysql')]
final class MySqlLedgerTest extends LedgerTestCase
{
    private Connection $connection;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = self::connect();

        foreach ([PointsTables::MOVEMENTS, PointsTables::LOCKS] as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $schema = PointsTables::configure($this->connection->createSchemaManager()->introspectSchema());

        foreach ($this->connection->getDatabasePlatform()->getCreateTablesSQL([$schema->getTable(PointsTables::MOVEMENTS), $schema->getTable(PointsTables::LOCKS)]) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    /**
     * The first payment commits while the second one is about to take the lock row: once the second holds the lock, it
     * reads the balance the first one left, not the one it would have read before.
     */
    public function testAPaymentReadsTheBalanceLeftByThePaymentBeforeItsLock(): void
    {
        $ledger = new DbalLedger($this->connection);
        $ledger->append(self::movement('in', MovementKind::Granted, 'issuance:manual', 'member:member:1', 100), self::at());

        $meanwhile = new Meanwhile('INSERT IGNORE INTO '.PointsTables::LOCKS, static function (): void {
            $first = self::connect();
            new DbalLedger($first)->appendCovered(self::movement('spend-1', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 100), self::at());
            $first->close();
        });
        $second = self::connect($meanwhile);

        try {
            new DbalLedger($second)->appendCovered(self::movement('spend-2', MovementKind::Spent, 'member:member:1', 'redemption:reward:2', 100), self::at());
            self::fail('Insufficient points were expected.');
        } catch (InsufficientPoints $insufficientPoints) {
            self::assertSame('The account "member:member:1" holds 0 points; 100 cannot be paid from it.', $insufficientPoints->getMessage());
        } finally {
            $second->close();
        }

        self::assertTrue($meanwhile->ran());
        self::assertSame(0, $ledger->balance(Account::fromString('member:member:1'), 'points'));
        self::assertNull($ledger->find(MovementKey::fromString('spend-2')));
    }

    /**
     * A payment made inside a transaction that has already read: the balance is still the one committed last.
     */
    public function testAPaymentInATransactionThatHasAlreadyReadSeesTheLatestBalance(): void
    {
        $ledger = new DbalLedger($this->connection);
        $ledger->append(self::movement('in', MovementKind::Granted, 'issuance:manual', 'member:member:1', 100), self::at());

        $this->connection->beginTransaction();
        self::assertSame(100, $ledger->balance(Account::fromString('member:member:1'), 'points'));

        $other = self::connect();
        new DbalLedger($other)->appendCovered(self::movement('spend-1', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 100), self::at());
        $other->close();

        try {
            $ledger->appendCovered(self::movement('spend-2', MovementKind::Spent, 'member:member:1', 'redemption:reward:2', 100), self::at());
            self::fail('Insufficient points were expected.');
        } catch (InsufficientPoints $insufficientPoints) {
            self::assertSame('The account "member:member:1" holds 0 points; 100 cannot be paid from it.', $insufficientPoints->getMessage());
        } finally {
            $this->connection->rollBack();
        }
    }

    /**
     * Two payments under the same key, the second inside a transaction that has already read: it finds the movement the
     * first one wrote.
     */
    public function testAPaymentInATransactionThatHasAlreadyReadFindsTheKeyAnotherWrote(): void
    {
        $ledger = new DbalLedger($this->connection);
        $ledger->append(self::movement('in', MovementKind::Granted, 'issuance:manual', 'member:member:1', 100), self::at());

        $this->connection->beginTransaction();
        $ledger->balance(Account::fromString('member:member:2'), 'points');

        $other = self::connect();
        new DbalLedger($other)->appendCovered(self::movement('spend', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 60), self::at());
        $other->close();

        try {
            $again = $ledger->appendCovered(self::movement('spend', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 60), self::at());
            self::assertFalse($again->new);
        } finally {
            $this->connection->rollBack();
        }

        self::assertSame(40, $ledger->balance(Account::fromString('member:member:1'), 'points'));
    }

    #[Override]
    protected function ledger(): Ledger
    {
        return new DbalLedger($this->connection);
    }

    private static function connect(?Meanwhile $meanwhile = null): Connection
    {
        $url = getenv('PHIL_TEST_MYSQL_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Set PHIL_TEST_MYSQL_URL to run this test against a real server.');
        }

        $configuration = new Configuration();

        if ($meanwhile instanceof Meanwhile) {
            $configuration->setMiddlewares([$meanwhile]);
        }

        return DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql'])->parse($url), $configuration);
    }
}
