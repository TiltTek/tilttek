<?php

declare(strict_types=1);

namespace Tilttek\PointsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Types;
use LogicException;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Points\Account;
use Tilttek\Points\AccountType;
use Tilttek\Points\Erasure;
use Tilttek\Points\Exception\InsufficientPoints;
use Tilttek\Points\Exception\MovementConflict;
use Tilttek\Points\Ledger\Ledger;
use Tilttek\Points\Ledger\Reading;
use Tilttek\Points\LedgerEntry;
use Tilttek\Points\Movement;
use Tilttek\Points\MovementKey;
use Tilttek\Points\MovementKind;
use Tilttek\Points\Recorded;
use UnexpectedValueException;

/**
 * The ledger in `tilttek_points_movement`, through a DBAL connection.
 *
 * A movement is inserted by a statement that writes nothing when its key is already there — written earlier, or by
 * another process at the same moment —, without aborting the surrounding transaction: `ON CONFLICT DO NOTHING` on
 * PostgreSQL, `INSERT IGNORE` on MySQL and MariaDB, `INSERT OR IGNORE` on SQLite; elsewhere, a plain insert, whose
 * unique violation, when another process wrote the same key in between, reaches the caller. The movement is then read
 * back, so the caller gets the row the database kept, and whether this call wrote it.
 *
 * A covered movement runs in a transaction that first updates the paying account's row of `tilttek_points_lock`,
 * which holds the row's write lock — or SQLite's lock of the whole database — until the transaction ends, before it
 * reads anything: two payments from the same account check the balance one after the other, and the second one reads
 * the movements the first one committed. On MySQL and MariaDB, the transaction runs at READ COMMITTED, so that its
 * reads are not held to a snapshot older than the lock; inside a transaction the caller opened, whose snapshot may be
 * older, the movement and the balance are read with `LOCK IN SHARE MODE`. Dates are written in UTC, to the second.
 */
final readonly class DbalLedger implements Ledger
{
    private const string INSERTED = 'movement_key, kind, currency, debit, credit, amount, occurred_at, recorded_at, cause, reverses';

    private const string COLUMNS = 'position, '.self::INSERTED;

    private const string BALANCE = 'SELECT COALESCE(SUM(CASE WHEN credit = ? THEN amount ELSE 0 - amount END), 0) FROM '.PointsTables::MOVEMENTS.' WHERE currency = ? AND (credit = ? OR debit = ?)';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function append(Movement $movement, DateTimeImmutable $at): Recorded
    {
        return $this->write($movement, $at, '');
    }

    #[Override]
    public function appendCovered(Movement $movement, DateTimeImmutable $at): Recorded
    {
        $platform = $this->connection->getDatabasePlatform();
        $mysql = $platform instanceof AbstractMySQLPlatform;
        // Inside a transaction the caller opened, whose snapshot may predate the lock, MySQL reads through locks.
        $current = $mysql && $this->connection->isTransactionActive();

        if ($mysql && !$current) {
            $this->connection->executeStatement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }

        return $this->connection->transactional(function (Connection $connection) use ($movement, $at, $platform, $current): Recorded {
            $account = $movement->debit->toString();
            $connection->executeStatement(self::lockInsertion($platform), [$account, $movement->currency]);
            $locked = $connection->executeStatement(\sprintf('UPDATE %s SET payments = payments + 1 WHERE account = ? AND currency = ?', PointsTables::LOCKS), [$account, $movement->currency]);

            if (1 !== $this->integer($locked)) {
                throw new LogicException(\sprintf('The lock row of "%s" in %s is neither written nor found.', $account, $movement->currency));
            }

            $lock = $current ? ' LOCK IN SHARE MODE' : '';

            if ($this->read($movement->key, $lock) instanceof LedgerEntry) {
                return $this->write($movement, $at, $lock);
            }

            $balance = $this->integer($connection->fetchOne(self::BALANCE.$lock, $this->balanceParameters($movement->debit, $movement->currency)));

            if ($balance < $movement->amount) {
                throw InsufficientPoints::in($movement->debit, $movement->currency, $balance, $movement->amount);
            }

            return $this->write($movement, $at, $lock);
        });
    }

    #[Override]
    public function find(MovementKey $key): ?LedgerEntry
    {
        return $this->read($key, '');
    }

    #[Override]
    public function causedBy(string $cause): array
    {
        return $this->entries(\sprintf('SELECT %s FROM %s WHERE cause = ? ORDER BY position', self::COLUMNS, PointsTables::MOVEMENTS), [$cause]);
    }

    #[Override]
    public function balance(Account $account, string $currency): int
    {
        return $this->integer($this->connection->fetchOne(self::BALANCE, $this->balanceParameters($account, $currency)));
    }

    #[Override]
    public function net(Account $to, Account $from, string $currency, ?DateTimeImmutable $since = null, ?DateTimeImmutable $until = null): int
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(\sprintf('COALESCE(SUM(CASE WHEN credit = %s THEN amount ELSE 0 - amount END), 0)', $query->createNamedParameter($to->toString())))
            ->from(PointsTables::MOVEMENTS)
            ->where('currency = '.$query->createNamedParameter($currency))
            ->andWhere(\sprintf(
                '((credit = %1$s AND debit = %2$s) OR (debit = %1$s AND credit = %2$s))',
                $query->createNamedParameter($to->toString()),
                $query->createNamedParameter($from->toString()),
            ));

        if ($since instanceof DateTimeImmutable) {
            $query->andWhere('occurred_at >= '.$query->createNamedParameter($this->utc($since), Types::DATETIME_IMMUTABLE));
        }

        if ($until instanceof DateTimeImmutable) {
            $query->andWhere('occurred_at < '.$query->createNamedParameter($this->utc($until), Types::DATETIME_IMMUTABLE));
        }

        return $this->integer($query->fetchOne());
    }

    #[Override]
    public function statement(Account $account, ?string $currency, int $after, int $limit): array
    {
        Reading::check($after, $limit);

        $query = $this->connection->createQueryBuilder()
            ->select(self::COLUMNS)
            ->from(PointsTables::MOVEMENTS)
            ->orderBy('position')
            ->setMaxResults($limit);
        $query->where('position > '.$query->createNamedParameter($after, ParameterType::INTEGER))
            ->andWhere(\sprintf('(credit = %1$s OR debit = %1$s)', $query->createNamedParameter($account->toString())));

        if (null !== $currency) {
            $query->andWhere('currency = '.$query->createNamedParameter($currency));
        }

        $entries = [];

        foreach ($query->fetchAllAssociative() as $row) {
            $entries[] = $this->hydrate($row);
        }

        return $entries;
    }

    #[Override]
    public function membersCreditedBefore(string $currency, DateTimeImmutable $cutoff): array
    {
        $accounts = $this->connection->fetchFirstColumn(
            \sprintf("SELECT DISTINCT credit FROM %s WHERE currency = ? AND credit LIKE '%s:%%' AND occurred_at <= ? ORDER BY credit", PointsTables::MOVEMENTS, AccountType::Member->value),
            [$currency, $this->utc($cutoff)],
            [ParameterType::STRING, Types::DATETIME_IMMUTABLE],
        );

        return array_map(fn (mixed $account): Account => Account::fromString($this->text($account)), $accounts);
    }

    #[Override]
    public function forgetDerived(?DateTimeImmutable $since): int
    {
        $kinds = array_map(static fn (MovementKind $kind): string => $kind->value, MovementKind::derived());
        $sql = \sprintf('DELETE FROM %s WHERE kind IN (?)', PointsTables::MOVEMENTS);

        if (!$since instanceof DateTimeImmutable) {
            return $this->integer($this->connection->executeStatement($sql, [$kinds], [ArrayParameterType::STRING]));
        }

        return $this->integer($this->connection->executeStatement($sql.' AND occurred_at >= ?', [$kinds, $this->utc($since)], [ArrayParameterType::STRING, Types::DATETIME_IMMUTABLE]));
    }

    #[Override]
    public function eraseMember(ResourceReference $member): Erasure
    {
        $account = Account::member($member)->toString();
        $members = AccountType::Member->value.':%';
        $erased = Account::erased()->toString();
        $table = PointsTables::MOVEMENTS;

        return $this->connection->transactional(function (Connection $connection) use ($account, $members, $erased, $table): Erasure {
            $deleted = $connection->executeStatement(
                \sprintf('DELETE FROM %s WHERE (debit = ? AND credit NOT LIKE ?) OR (credit = ? AND debit NOT LIKE ?)', $table),
                [$account, $members, $account, $members],
            );
            $detached = $this->integer($connection->executeStatement(\sprintf('UPDATE %s SET debit = ? WHERE debit = ?', $table), [$erased, $account]))
                + $this->integer($connection->executeStatement(\sprintf('UPDATE %s SET credit = ? WHERE credit = ?', $table), [$erased, $account]));
            $connection->executeStatement(\sprintf('DELETE FROM %s WHERE account = ?', PointsTables::LOCKS), [$account]);

            return new Erasure($this->integer($deleted), $detached);
        });
    }

    /**
     * The insertion of a movement that writes nothing when its key is already there, on this platform; a plain
     * insertion where the platform has no such statement.
     */
    public static function insertion(AbstractPlatform $platform): string
    {
        return self::ignoring($platform, \sprintf('INTO %s (%s) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', PointsTables::MOVEMENTS, self::INSERTED), 'movement_key');
    }

    /**
     * The insertion of an account's lock row that writes nothing when the row is already there.
     */
    public static function lockInsertion(AbstractPlatform $platform): string
    {
        return self::ignoring($platform, \sprintf('INTO %s (account, currency, payments) VALUES (?, ?, 0)', PointsTables::LOCKS), 'account, currency');
    }

    private static function ignoring(AbstractPlatform $platform, string $into, string $unique): string
    {
        return match (true) {
            $platform instanceof PostgreSQLPlatform => 'INSERT '.$into.' ON CONFLICT ('.$unique.') DO NOTHING',
            $platform instanceof AbstractMySQLPlatform => 'INSERT IGNORE '.$into,
            $platform instanceof SQLitePlatform => 'INSERT OR IGNORE '.$into,
            default => 'INSERT '.$into,
        };
    }

    /**
     * Writes a movement unless its key is already there, reading with the given locking clause.
     */
    private function write(Movement $movement, DateTimeImmutable $at, string $lock): Recorded
    {
        // A movement already there is not inserted again: an insertion that writes nothing may still use up a
        // position.
        $entry = $this->read($movement->key, $lock);
        $new = false;

        if (!$entry instanceof LedgerEntry) {
            $new = $this->insert($movement, $at);
            $entry = $this->read($movement->key, $lock) ?? throw new LogicException(\sprintf('The movement "%s" is neither written nor found.', $movement->key->value));
        }

        if (!$entry->movement->sameAs($movement)) {
            throw MovementConflict::on($movement->key);
        }

        return new Recorded($entry, $new);
    }

    private function read(MovementKey $key, string $lock): ?LedgerEntry
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT %s FROM %s WHERE movement_key = ?', self::COLUMNS, PointsTables::MOVEMENTS).$lock, [$key->value]);

        return false === $row ? null : $this->hydrate($row);
    }

    /**
     * @return list<string>
     */
    private function balanceParameters(Account $account, string $currency): array
    {
        return [$account->toString(), $currency, $account->toString(), $account->toString()];
    }

    private function insert(Movement $movement, DateTimeImmutable $at): bool
    {
        $values = [
            $movement->key->value,
            $movement->kind->value,
            $movement->currency,
            $movement->debit->toString(),
            $movement->credit->toString(),
            $movement->amount,
            $this->utc($movement->occurredAt),
            $this->utc($at),
            $movement->cause,
            $movement->reverses?->value,
        ];
        $types = [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, Types::DATETIME_IMMUTABLE, Types::DATETIME_IMMUTABLE, ParameterType::STRING, ParameterType::STRING];

        return 0 < $this->connection->executeStatement(self::insertion($this->connection->getDatabasePlatform()), $values, $types);
    }

    /**
     * @param list<string> $parameters
     *
     * @return list<LedgerEntry>
     */
    private function entries(string $sql, array $parameters): array
    {
        return array_map($this->hydrate(...), $this->connection->fetchAllAssociative($sql, $parameters));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): LedgerEntry
    {
        return new LedgerEntry(
            $this->integer($row['position']),
            new Movement(
                MovementKey::fromString($this->text($row['movement_key'])),
                MovementKind::from($this->text($row['kind'])),
                $this->text($row['currency']),
                Account::fromString($this->text($row['debit'])),
                Account::fromString($this->text($row['credit'])),
                $this->integer($row['amount']),
                $this->date($row['occurred_at']),
                null === $row['cause'] ? null : $this->text($row['cause']),
                null === $row['reverses'] ? null : MovementKey::fromString($this->text($row['reverses'])),
            ),
            $this->date($row['recorded_at']),
        );
    }

    private function utc(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->setTimezone(new DateTimeZone('UTC'));
    }

    private function date(mixed $value): DateTimeImmutable
    {
        return new DateTimeImmutable($this->text($value), new DateTimeZone('UTC'));
    }

    private function text(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new UnexpectedValueException(\sprintf('A text column holds %s.', get_debug_type($value)));
        }

        return $value;
    }

    private function integer(mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && 1 === preg_match('/^-?\d+$/D', $value)) {
            return (int) $value;
        }

        throw new UnexpectedValueException(\sprintf('An integer column holds %s.', get_debug_type($value)));
    }
}
