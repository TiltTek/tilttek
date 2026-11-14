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
 * statement that writes nothing when the row is already there. The `notice` column holds the notice the observer is
 * still owed: `awarded` for an award whose notice is due, `revoked` for a withdrawn award, none otherwise.
 */
final readonly class DbalAwardStore implements AwardStore
{
    private const string COLUMNS = 'badge, member, number, awarded_at, cause, manual';

    private const string AWARDED = 'awarded';

    private const string REVOKED = 'revoked';

    private const string KEY = 'member = ? AND badge = ? AND number = ?';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(Award $award): bool
    {
        $inserted = $this->connection->executeStatement(
            Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (%s, notice) VALUES (?, ?, ?, ?, ?, ?, ?)', BadgesTables::AWARDS, self::COLUMNS), 'member, badge, number'),
            [$award->badge, $award->member->toString(), $award->number, Rows::utc($award->awardedAt), $award->cause, $award->manual, $award->manual ? null : self::AWARDED],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, Types::DATETIME_IMMUTABLE, ParameterType::STRING, ParameterType::BOOLEAN, ParameterType::STRING],
        );

        return 0 < $inserted;
    }

    #[Override]
    public function markDelivered(Award $award): void
    {
        $this->connection->executeStatement(\sprintf('UPDATE %s SET notice = NULL WHERE %s AND notice = ?', BadgesTables::AWARDS, self::KEY), [...$this->key($award), self::AWARDED]);
    }

    #[Override]
    public function undelivered(ResourceReference $member): array
    {
        return $this->noticed($member, self::AWARDED);
    }

    #[Override]
    public function withdraw(Award $award): bool
    {
        // One atomic statement: the award held, whose own notice may be under way in another process, waits for the
        // notice of its withdrawal; markDelivered() only clears an `awarded` notice, and leaves this one due.
        return 0 < $this->connection->executeStatement(\sprintf('UPDATE %s SET notice = ? WHERE %s AND (notice IS NULL OR notice = ?)', BadgesTables::AWARDS, self::KEY), [self::REVOKED, ...$this->key($award), self::AWARDED]);
    }

    #[Override]
    public function withdrawn(ResourceReference $member): array
    {
        return $this->noticed($member, self::REVOKED);
    }

    #[Override]
    public function remove(Award $award): void
    {
        $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE %s', BadgesTables::AWARDS, self::KEY), $this->key($award));
    }

    #[Override]
    public function of(ResourceReference $member, ?string $badge = null): array
    {
        $query = $this->connection->createQueryBuilder()->select(self::COLUMNS)->from(BadgesTables::AWARDS)->orderBy('awarded_at')->addOrderBy('badge')->addOrderBy('number');
        $query->where('member = '.$query->createNamedParameter($member->toString()));
        $query->andWhere('(notice IS NULL OR notice = '.$query->createNamedParameter(self::AWARDED).')');

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

            $forgotten = array_map($this->hydrate(...), $connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE %s AND notice IS NULL ORDER BY awarded_at, badge, number', self::COLUMNS, BadgesTables::AWARDS, $where), $parameters, $types));
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
     * @return array{string, string, int}
     */
    private function key(Award $award): array
    {
        return [$award->member->toString(), $award->badge, $award->number];
    }

    /**
     * The member's awards that wait for this notice, by badge and number.
     *
     * @return list<Award>
     */
    private function noticed(ResourceReference $member, string $notice): array
    {
        return array_map($this->hydrate(...), $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE member = ? AND notice = ? ORDER BY badge, number', self::COLUMNS, BadgesTables::AWARDS), [$member->toString(), $notice]));
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
