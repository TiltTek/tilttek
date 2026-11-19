<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Erasure;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Transfer\Transfer;
use Tilttek\Exchange\Transfer\TransferLog;

/**
 * The journal of transfers in `tilttek_exchange_transfer`, through a DBAL connection: a transfer is inserted by a
 * statement that writes nothing when its key is already there.
 */
final readonly class DbalTransferLog implements TransferLog
{
    private const string COLUMNS = 'transfer_key, from_member, to_member, amount, currency, transferred_at';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function record(Transfer $transfer): bool
    {
        return 0 < $this->connection->executeStatement(
            Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (%s) VALUES (?, ?, ?, ?, ?, ?)', ExchangeTables::TRANSFERS, self::COLUMNS), 'transfer_key'),
            [$transfer->key->value, $transfer->from->toString(), $transfer->to->toString(), $transfer->amount, $transfer->currency, $transfer->at],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING, Types::DATETIME_UTC_IMMUTABLE],
        );
    }

    #[Override]
    public function find(ExchangeKey $key): ?Transfer
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT %s FROM %s WHERE transfer_key = ?', self::COLUMNS, ExchangeTables::TRANSFERS), [$key->value]);

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function given(ResourceReference $member, string $currency, ?DateTimeImmutable $since, ?DateTimeImmutable $until): int
    {
        $query = $this->connection->createQueryBuilder()
            ->select('COALESCE(SUM(amount), 0)')
            ->from(ExchangeTables::TRANSFERS);
        $query->where('from_member = '.$query->createNamedParameter($member->toString()))
            ->andWhere('currency = '.$query->createNamedParameter($currency));

        if ($since instanceof DateTimeImmutable) {
            $query->andWhere('transferred_at >= '.$query->createNamedParameter($since, Types::DATETIME_UTC_IMMUTABLE));
        }

        if ($until instanceof DateTimeImmutable) {
            $query->andWhere('transferred_at < '.$query->createNamedParameter($until, Types::DATETIME_UTC_IMMUTABLE));
        }

        return Rows::integer($query->fetchOne());
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return $this->hydrateAll($this->connection->fetchAllAssociative(
            \sprintf('SELECT %s FROM %s WHERE from_member = ? OR to_member = ? ORDER BY position', self::COLUMNS, ExchangeTables::TRANSFERS),
            [$member->toString(), $member->toString()],
        ));
    }

    #[Override]
    public function latest(int $limit): array
    {
        return $this->hydrateAll($this->connection->createQueryBuilder()
            ->select(self::COLUMNS)
            ->from(ExchangeTables::TRANSFERS)
            ->orderBy('position', 'DESC')
            ->setMaxResults($limit)
            ->fetchAllAssociative());
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $erased = Erasure::ERASED;

        return $this->connection->transactional(static fn (Connection $connection): int => Rows::integer($connection->executeStatement(\sprintf('UPDATE %s SET from_member = ? WHERE from_member = ?', ExchangeTables::TRANSFERS), [$erased, $member->toString()]))
            + Rows::integer($connection->executeStatement(\sprintf('UPDATE %s SET to_member = ? WHERE to_member = ?', ExchangeTables::TRANSFERS), [$erased, $member->toString()])));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return list<Transfer>
     */
    private function hydrateAll(array $rows): array
    {
        $values = [];

        foreach ($rows as $row) {
            $values[] = $this->hydrate($row);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Transfer
    {
        return new Transfer(
            ExchangeKey::fromString(Rows::text($row['transfer_key'])),
            Rows::reference($row['from_member']),
            Rows::reference($row['to_member']),
            Rows::integer($row['amount']),
            Rows::text($row['currency']),
            Rows::date($row['transferred_at']),
        );
    }
}
