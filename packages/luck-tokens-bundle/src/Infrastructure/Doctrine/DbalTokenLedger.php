<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\Contract\TokenLedger;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\Exception\LedgerConflict;
use Tilttek\LuckTokens\Ledger\EntryType;
use Tilttek\LuckTokens\Ledger\LedgerEntry;

/**
 * The ledgers in `tilttek_luck_tokens_entry`, through a DBAL connection. The entries of an operation are inserted in
 * one transaction, after the last position is read again there: a writer that finds another position, or whose
 * insertion meets an entry of the same table and position — the primary key — or a deadlock, writes nothing and throws
 * `LedgerConflict`, which `LuckTokens` answers by reading again. A balance is read from the holder's last entry of the
 * kind, by its index, never by adding up the ledger.
 *
 * The store keeps nothing between two calls: one instance serves every request of a worker.
 */
final readonly class DbalTokenLedger implements TokenLedger
{
    private const string COLUMNS = 'table_ref, position, operation_id, entry_type, token_kind, holder, delta, balance, actor, written_at, reason, session_key';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function entries(ResourceReference $table): array
    {
        return $this->hydrate($this->connection->fetchAllAssociative(
            \sprintf('SELECT %s FROM %s WHERE table_ref = ? ORDER BY position', self::COLUMNS, LuckTokensTables::ENTRIES),
            [$table->toString()],
        ));
    }

    #[Override]
    public function last(ResourceReference $table): int
    {
        return self::lastOn($this->connection, $table);
    }

    #[Override]
    public function balance(ResourceReference $table, string $kind, ResourceReference $holder): int
    {
        $balance = $this->connection->createQueryBuilder()
            ->select('balance')
            ->from(LuckTokensTables::ENTRIES)
            ->where('table_ref = ?')
            ->andWhere('token_kind = ?')
            ->andWhere('holder = ?')
            ->orderBy('position', 'DESC')
            ->setMaxResults(1)
            ->setParameters([$table->toString(), $kind, $holder->toString()])
            ->fetchOne();

        return false === $balance ? 0 : Rows::integer($balance);
    }

    #[Override]
    public function holders(ResourceReference $table): array
    {
        return array_map(
            static fn (mixed $holder): ResourceReference => ResourceReference::fromString(Rows::text($holder)),
            $this->connection->fetchFirstColumn(
                \sprintf('SELECT holder FROM %s WHERE table_ref = ? AND token_kind IS NOT NULL GROUP BY holder ORDER BY MIN(position)', LuckTokensTables::ENTRIES),
                [$table->toString()],
            ),
        );
    }

    #[Override]
    public function operation(ResourceReference $table, string $operation): array
    {
        return $this->hydrate($this->connection->fetchAllAssociative(
            \sprintf('SELECT %s FROM %s WHERE table_ref = ? AND operation_id = ? ORDER BY position', self::COLUMNS, LuckTokensTables::ENTRIES),
            [$table->toString(), $operation],
        ));
    }

    #[Override]
    public function hasSession(ResourceReference $table, string $session): bool
    {
        return false !== $this->connection->createQueryBuilder()
            ->select('position')
            ->from(LuckTokensTables::ENTRIES)
            ->where('table_ref = ?')
            ->andWhere('session_key = ?')
            ->andWhere('entry_type = ?')
            ->setMaxResults(1)
            ->setParameters([$table->toString(), $session, EntryType::SessionStarted->value])
            ->fetchOne();
    }

    #[Override]
    public function append(ResourceReference $table, int $after, array $entries): void
    {
        try {
            $this->connection->transactional(static function (Connection $connection) use ($table, $after, $entries): void {
                $last = self::lastOn($connection, $table);

                if ($after !== $last) {
                    throw LedgerConflict::after($table, $after, $last);
                }

                foreach ($entries as $index => $entry) {
                    if ($entry->position !== $after + $index + 1 || !$entry->table->equals($table)) {
                        throw InvalidTokenRule::because('The entries of an operation belong to the table of the ledger and follow its last one, by position.');
                    }
                }

                foreach ($entries as $entry) {
                    $connection->insert(LuckTokensTables::ENTRIES, self::row($entry), ['position' => ParameterType::INTEGER, 'delta' => ParameterType::INTEGER, 'balance' => ParameterType::INTEGER]);
                }
            });
        } catch (UniqueConstraintViolationException|RetryableException) {
            throw LedgerConflict::after($table, $after, $this->last($table));
        }
    }

    #[Override]
    public function eraseHolder(ResourceReference $member, ResourceReference $pseudonym): int
    {
        if ($member->equals($pseudonym)) {
            throw InvalidTokenRule::because('A member is erased under a pseudonym of their own, not their reference.');
        }

        $from = $member->toString();
        $to = $pseudonym->toString();

        return $this->connection->transactional(static function (Connection $connection) use ($from, $to): int {
            $erased = Rows::integer($connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE holder = ? OR actor = ?', LuckTokensTables::ENTRIES), [$from, $from]));
            $connection->executeStatement(\sprintf('UPDATE %s SET holder = ?, reason = NULL WHERE holder = ?', LuckTokensTables::ENTRIES), [$to, $from]);
            $connection->executeStatement(\sprintf('UPDATE %s SET actor = ? WHERE actor = ?', LuckTokensTables::ENTRIES), [$to, $from]);

            return $erased;
        });
    }

    private static function lastOn(Connection $connection, ResourceReference $table): int
    {
        return Rows::integer($connection->fetchOne(\sprintf('SELECT COALESCE(MAX(position), 0) FROM %s WHERE table_ref = ?', LuckTokensTables::ENTRIES), [$table->toString()]));
    }

    /**
     * @return array<string, int|string|null>
     */
    private static function row(LedgerEntry $entry): array
    {
        $values = $entry->toArray();

        return [
            'table_ref' => $values['table'],
            'position' => $values['position'],
            'operation_id' => $values['operation'],
            'entry_type' => $values['type'],
            'token_kind' => $values['kind'],
            'holder' => $values['holder'],
            'delta' => $values['change'],
            'balance' => $values['balance'],
            'actor' => $values['actor'],
            'written_at' => $values['at'],
            'reason' => $values['reason'],
            'session_key' => $values['session'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<LedgerEntry>
     */
    private function hydrate(array $rows): array
    {
        return array_map(static fn (array $row): LedgerEntry => LedgerEntry::fromArray([
            'table' => $row['table_ref'],
            'position' => Rows::integer($row['position']),
            'operation' => $row['operation_id'],
            'type' => $row['entry_type'],
            'kind' => $row['token_kind'],
            'holder' => $row['holder'],
            'change' => Rows::integer($row['delta']),
            'balance' => Rows::integer($row['balance']),
            'actor' => $row['actor'],
            'at' => $row['written_at'],
            'reason' => $row['reason'],
            'session' => $row['session_key'],
        ]), $rows);
    }
}
