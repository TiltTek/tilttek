<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\Contract\SignalStore;
use Tilttek\TableSafety\Signal\Audience;
use Tilttek\TableSafety\Signal\Signal;
use Tilttek\TableSafety\Signal\SignalKind;

/**
 * The signals in `tilttek_table_safety_signal`, through a DBAL connection. An anonymous signal's row has no author;
 * the last signals of a table, and its last check-in with every answer after it, are read through the index on the
 * table and the microsecond they were raised at, so that two signals of the same second keep their order. The moments read back are to the second.
 */
final readonly class DbalSignalStore implements SignalStore
{
    private const string COLUMNS = 'id, table_ref, kind, audience, raised_at, raised_by, acknowledged_at';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(Signal $signal): void
    {
        $this->connection->insert(TableSafetyTables::SIGNALS, [
            'id' => $signal->id,
            'table_ref' => $signal->table->toString(),
            'kind' => $signal->kind->value,
            'audience' => $signal->audience->value,
            'raised_at' => Rows::utc($signal->raisedAt),
            'raised_us' => Rows::microseconds($signal->raisedAt),
            'raised_by' => $signal->raisedBy?->toString(),
            'acknowledged_at' => $signal->acknowledgedAt instanceof DateTimeImmutable ? Rows::utc($signal->acknowledgedAt) : null,
        ], [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, Types::DATETIME_IMMUTABLE, ParameterType::INTEGER, ParameterType::STRING, Types::DATETIME_IMMUTABLE]);
    }

    #[Override]
    public function acknowledge(ResourceReference $table, string $id, DateTimeImmutable $at): ?Signal
    {
        $changed = $this->connection->executeStatement(
            \sprintf('UPDATE %s SET acknowledged_at = ? WHERE id = ? AND table_ref = ? AND acknowledged_at IS NULL', TableSafetyTables::SIGNALS),
            [Rows::utc($at), $id, $table->toString()],
            [Types::DATETIME_IMMUTABLE, ParameterType::STRING, ParameterType::STRING],
        );

        return 0 === $changed ? null : $this->where('id = ?', [$id], 'id')[0] ?? null;
    }

    #[Override]
    public function recent(ResourceReference $table, ?DateTimeImmutable $after = null, int $limit = 100): array
    {
        $condition = $after instanceof DateTimeImmutable ? 'table_ref = ? AND raised_us > ?' : 'table_ref = ?';
        $parameters = $after instanceof DateTimeImmutable ? [$table->toString(), Rows::microseconds($after)] : [$table->toString()];

        return array_reverse($this->where($condition, $parameters, 'raised_us DESC, id DESC', $limit, [ParameterType::STRING, ParameterType::INTEGER]));
    }

    #[Override]
    public function lastCheckIn(ResourceReference $table): array
    {
        $checkIn = $this->where('table_ref = ? AND kind = ?', [$table->toString(), SignalKind::CheckIn->value], 'raised_us DESC, id DESC', 1)[0] ?? null;

        if (!$checkIn instanceof Signal) {
            return [];
        }

        $raisedUs = Rows::microseconds($checkIn->raisedAt);
        $answers = $this->where(
            'table_ref = ? AND kind IN (?, ?, ?) AND (raised_us > ? OR (raised_us = ? AND id > ?))',
            [$table->toString(), SignalKind::AllGood->value, SignalKind::Unsure->value, SignalKind::NotOkay->value, $raisedUs, $raisedUs, $checkIn->id],
            'raised_us, id',
            null,
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING],
        );

        return [$checkIn, ...$answers];
    }

    #[Override]
    public function ofParticipant(ResourceReference $participant): array
    {
        return $this->where('raised_by = ?', [$participant->toString()], 'raised_us, id');
    }

    #[Override]
    public function forgetParticipant(ResourceReference $participant): int
    {
        return (int) $this->connection->update(TableSafetyTables::SIGNALS, ['raised_by' => null], ['raised_by' => $participant->toString()]);
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        return (int) $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE raised_at < ?', TableSafetyTables::SIGNALS), [Rows::utc($before)], [Types::DATETIME_IMMUTABLE]);
    }

    /**
     * @param list<string|int>           $parameters
     * @param list<ParameterType|string> $types
     *
     * @return list<Signal>
     */
    private function where(string $condition, array $parameters, string $order, ?int $limit = null, array $types = []): array
    {
        $sql = \sprintf('SELECT %s FROM %s WHERE %s ORDER BY %s', self::COLUMNS, TableSafetyTables::SIGNALS, $condition, $order);
        $signals = [];

        foreach ($this->connection->fetchAllAssociative(null === $limit ? $sql : $this->connection->getDatabasePlatform()->modifyLimitQuery($sql, $limit), $parameters, $types) as $row) {
            $signals[] = new Signal(
                Rows::text($row['id']),
                ResourceReference::fromString(Rows::text($row['table_ref'])),
                SignalKind::from(Rows::text($row['kind'])),
                Audience::from(Rows::text($row['audience'])),
                Rows::date($row['raised_at']),
                null === $row['raised_by'] ? null : ResourceReference::fromString(Rows::text($row['raised_by'])),
                null === $row['acknowledged_at'] ? null : Rows::date($row['acknowledged_at']),
            );
        }

        return $signals;
    }
}
