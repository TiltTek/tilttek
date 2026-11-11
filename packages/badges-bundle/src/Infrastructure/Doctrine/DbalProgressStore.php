<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Badges\Contribution;
use Tilttek\Badges\Store\ProgressStore;

/**
 * The contributions in `tilttek_badges_contribution`, through a DBAL connection: one row per badge, path and activity,
 * inserted by a statement that writes nothing when the row is already there.
 */
final readonly class DbalProgressStore implements ProgressStore
{
    private const string COLUMNS = 'badge, path, activity, member, quantity, occurred_at';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(Contribution $contribution): bool
    {
        $inserted = $this->connection->executeStatement(
            Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (%s) VALUES (?, ?, ?, ?, ?, ?)', BadgesTables::CONTRIBUTIONS, self::COLUMNS), 'badge, path, activity'),
            [$contribution->badge, $contribution->path, $contribution->activity, $contribution->member->toString(), $contribution->quantity, Rows::utc($contribution->occurredAt)],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, Types::DATETIME_IMMUTABLE],
        );

        return 0 < $inserted;
    }

    #[Override]
    public function remove(string $activity): array
    {
        return $this->connection->transactional(static function (Connection $connection) use ($activity): array {
            $removed = array_map(
                static fn (array $row): Contribution => new Contribution(
                    Rows::text($row['badge']),
                    Rows::text($row['path']),
                    ResourceReference::fromString(Rows::text($row['member'])),
                    Rows::text($row['activity']),
                    Rows::integer($row['quantity']),
                    Rows::date($row['occurred_at']),
                ),
                $connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE activity = ? ORDER BY badge, path', self::COLUMNS, BadgesTables::CONTRIBUTIONS), [$activity]),
            );
            $connection->executeStatement(\sprintf('DELETE FROM %s WHERE activity = ?', BadgesTables::CONTRIBUTIONS), [$activity]);

            return $removed;
        });
    }

    #[Override]
    public function count(string $badge, string $path, ResourceReference $member): int
    {
        return Rows::integer($this->connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE member = ? AND badge = ? AND path = ?', BadgesTables::CONTRIBUTIONS), [$member->toString(), $badge, $path]));
    }

    #[Override]
    public function quantity(string $badge, string $path, ResourceReference $member): int
    {
        return Rows::integer($this->connection->fetchOne(\sprintf('SELECT COALESCE(SUM(quantity), 0) FROM %s WHERE member = ? AND badge = ? AND path = ?', BadgesTables::CONTRIBUTIONS), [$member->toString(), $badge, $path]));
    }

    #[Override]
    public function moments(string $badge, string $path, ResourceReference $member): array
    {
        return array_map(
            Rows::date(...),
            $this->connection->fetchFirstColumn(\sprintf('SELECT occurred_at FROM %s WHERE member = ? AND badge = ? AND path = ? ORDER BY occurred_at', BadgesTables::CONTRIBUTIONS), [$member->toString(), $badge, $path]),
        );
    }

    #[Override]
    public function forget(?DateTimeImmutable $since): int
    {
        if (!$since instanceof DateTimeImmutable) {
            return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s', BadgesTables::CONTRIBUTIONS)));
        }

        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE occurred_at >= ?', BadgesTables::CONTRIBUTIONS), [Rows::utc($since)], [Types::DATETIME_IMMUTABLE]));
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', BadgesTables::CONTRIBUTIONS), [$member->toString()]));
    }
}
