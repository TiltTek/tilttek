<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\Placing;
use Tilttek\Leaderboards\Snapshot;
use Tilttek\Leaderboards\Store\SnapshotStore;

/**
 * The snapshots in `tilttek_leaderboards_snapshot` and their lines in `tilttek_leaderboards_placing`, through a DBAL
 * connection: a snapshot is inserted by a statement that writes nothing when the board already has one for that
 * period, and its lines in the same transaction.
 */
final readonly class DbalSnapshotStore implements SnapshotStore
{
    /**
     * The columns of a line, `placing` — a reserved word of PostgreSQL — quoted by the platform where `%s` stands.
     */
    private const string PLACING_COLUMNS = 'board, period, member, %s, score, reached_at';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function save(Snapshot $snapshot): bool
    {
        return $this->connection->transactional(static function (Connection $connection) use ($snapshot): bool {
            $saved = 0 < $connection->executeStatement(
                Rows::ignoring($connection->getDatabasePlatform(), \sprintf('INTO %s (board, period, taken_at, participants) VALUES (?, ?, ?, ?)', LeaderboardsTables::SNAPSHOTS), 'board, period'),
                [$snapshot->board, $snapshot->period, $snapshot->takenAt, $snapshot->participants],
                [ParameterType::STRING, ParameterType::STRING, Types::DATETIME_UTC_IMMUTABLE, ParameterType::INTEGER],
            );

            foreach ($saved ? $snapshot->placings : [] as $placing) {
                $connection->executeStatement(
                    \sprintf('INSERT INTO %s (%s) VALUES (?, ?, ?, ?, ?, ?)', LeaderboardsTables::PLACINGS, self::columns($connection)),
                    [$placing->board, $placing->period, $placing->member->toString(), $placing->rank, $placing->value, $placing->reachedAt],
                    [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER, Types::DATETIME_UTC_IMMUTABLE],
                );
            }

            return $saved;
        });
    }

    #[Override]
    public function find(string $board, string $period): ?Snapshot
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT taken_at, participants FROM %s WHERE board = ? AND period = ?', LeaderboardsTables::SNAPSHOTS), [$board, $period]);

        if (false === $row) {
            return null;
        }

        return new Snapshot($board, $period, Rows::date($row['taken_at']), Rows::integer($row['participants']), $this->placingsWhere(\sprintf('board = ? AND period = ? ORDER BY %s, member', self::placing($this->connection)), [$board, $period]));
    }

    #[Override]
    public function placings(ResourceReference $member): array
    {
        return $this->placingsWhere('member = ? ORDER BY board, period', [$member->toString()]);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', LeaderboardsTables::PLACINGS), [$member->toString()]));
    }

    /**
     * @param list<string> $parameters
     *
     * @return list<Placing>
     */
    private function placingsWhere(string $condition, array $parameters): array
    {
        return array_map(
            static fn (array $row): Placing => new Placing(
                Rows::text($row['board']),
                Rows::text($row['period']),
                ResourceReference::fromString(Rows::text($row['member'])),
                Rows::integer($row['placing']),
                Rows::integer($row['score']),
                Rows::date($row['reached_at']),
            ),
            $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE %s', self::columns($this->connection), LeaderboardsTables::PLACINGS, $condition), $parameters),
        );
    }

    private static function columns(Connection $connection): string
    {
        return \sprintf(self::PLACING_COLUMNS, self::placing($connection));
    }

    private static function placing(Connection $connection): string
    {
        return $connection->getDatabasePlatform()->quoteSingleIdentifier('placing');
    }
}
