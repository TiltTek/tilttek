<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Badges\Award;
use Tilttek\Badges\Store\AwardStore;

/**
 * The awards in `tilttek_badges_award`, through a DBAL connection: one row per member, badge and number, inserted by a
 * statement that writes nothing when the row is already there.
 */
final readonly class DbalAwardStore implements AwardStore
{
    private const string COLUMNS = 'badge, member, number, awarded_at, cause, manual';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(Award $award): bool
    {
        $inserted = $this->connection->executeStatement(
            Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (%s) VALUES (?, ?, ?, ?, ?, ?)', BadgesTables::AWARDS, self::COLUMNS), 'member, badge, number'),
            [$award->badge, $award->member->toString(), $award->number, Rows::utc($award->awardedAt), $award->cause, $award->manual],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, Types::DATETIME_IMMUTABLE, ParameterType::STRING, ParameterType::BOOLEAN],
        );

        return 0 < $inserted;
    }

    #[Override]
    public function remove(Award $award): void
    {
        $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ? AND badge = ? AND number = ?', BadgesTables::AWARDS), [$award->member->toString(), $award->badge, $award->number], [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER]);
    }

    #[Override]
    public function of(ResourceReference $member, ?string $badge = null): array
    {
        $query = $this->connection->createQueryBuilder()->select(self::COLUMNS)->from(BadgesTables::AWARDS)->orderBy('awarded_at')->addOrderBy('badge')->addOrderBy('number');
        $query->where('member = '.$query->createNamedParameter($member->toString()));

        if (null !== $badge) {
            $query->andWhere('badge = '.$query->createNamedParameter($badge));
        }

        $awards = [];

        foreach ($query->fetchAllAssociative() as $row) {
            $awards[] = $this->hydrate($row);
        }

        return $awards;
    }

    #[Override]
    public function forgetDerived(?DateTimeImmutable $since): array
    {
        return $this->connection->transactional(function (Connection $connection) use ($since): array {
            $where = 'manual = ?';
            $parameters = [false];
            $types = [ParameterType::BOOLEAN];

            if ($since instanceof DateTimeImmutable) {
                $where .= ' AND awarded_at >= ?';
                $parameters[] = Rows::utc($since);
                $types[] = Types::DATETIME_IMMUTABLE;
            }

            $forgotten = array_map($this->hydrate(...), $connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE %s ORDER BY awarded_at, badge, number', self::COLUMNS, BadgesTables::AWARDS, $where), $parameters, $types));
            $connection->executeStatement(\sprintf('DELETE FROM %s WHERE %s', BadgesTables::AWARDS, $where), $parameters, $types);

            return $forgotten;
        });
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', BadgesTables::AWARDS), [$member->toString()]));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Award
    {
        return new Award(
            Rows::text($row['badge']),
            ResourceReference::fromString(Rows::text($row['member'])),
            Rows::integer($row['number']),
            Rows::date($row['awarded_at']),
            null === $row['cause'] ? null : Rows::text($row['cause']),
            true === $this->connection->convertToPHPValue($row['manual'], Types::BOOLEAN),
        );
    }
}
