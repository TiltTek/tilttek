<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Contribution;
use Tilttek\Quests\Store\ContributionStore;

/**
 * The contributions in `tilttek_quests_contribution`, through a DBAL connection: one row per quest, step and
 * activity, inserted by a statement that writes nothing when the row is already there.
 */
final readonly class DbalContributionStore implements ContributionStore
{
    private const string COLUMNS = 'quest, step, member, activity, value, occurred_at';

    private const string ORDER = 'ORDER BY occurred_at, activity, quest, step';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(Contribution $contribution): bool
    {
        return 0 < $this->connection->executeStatement(
            Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (%s) VALUES (?, ?, ?, ?, ?, ?)', QuestsTables::CONTRIBUTIONS, self::COLUMNS), 'quest, step, activity'),
            [$contribution->quest, $contribution->step, $contribution->member->toString(), $contribution->activity, $contribution->value, Rows::utc($contribution->occurredAt)],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, Types::DATETIME_IMMUTABLE],
        );
    }

    #[Override]
    public function remove(string $activity): array
    {
        $removed = $this->read('WHERE activity = ?', [$activity]);
        $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE activity = ?', QuestsTables::CONTRIBUTIONS), [$activity]);

        return $removed;
    }

    #[Override]
    public function of(string $quest, ?array $members = null, ?DateTimeImmutable $from = null, ?DateTimeImmutable $until = null): array
    {
        $where = ['quest = ?'];
        $parameters = [$quest];
        $types = [ParameterType::STRING];

        if (null !== $members) {
            $where[] = 'member IN (?)';
            $parameters[] = array_map(static fn (ResourceReference $member): string => $member->toString(), $members);
            $types[] = ArrayParameterType::STRING;
        }

        if ($from instanceof DateTimeImmutable) {
            $where[] = 'occurred_at >= ?';
            $parameters[] = Rows::utc($from);
            $types[] = Types::DATETIME_IMMUTABLE;
        }

        if ($until instanceof DateTimeImmutable) {
            $where[] = 'occurred_at < ?';
            $parameters[] = Rows::utc($until);
            $types[] = Types::DATETIME_IMMUTABLE;
        }

        return $this->read('WHERE '.implode(' AND ', $where), $parameters, $types);
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return $this->read('WHERE member = ?', [$member->toString()]);
    }

    #[Override]
    public function forget(?DateTimeImmutable $since): int
    {
        if (!$since instanceof DateTimeImmutable) {
            return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s', QuestsTables::CONTRIBUTIONS)));
        }

        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE occurred_at >= ?', QuestsTables::CONTRIBUTIONS), [Rows::utc($since)], [Types::DATETIME_IMMUTABLE]));
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', QuestsTables::CONTRIBUTIONS), [$member->toString()]));
    }

    /**
     * @param list<mixed>                                   $parameters
     * @param list<ParameterType|ArrayParameterType|string> $types
     *
     * @return list<Contribution>
     */
    private function read(string $where, array $parameters, array $types = []): array
    {
        return array_map(
            static fn (array $row): Contribution => new Contribution(
                Rows::text($row['quest']),
                Rows::text($row['step']),
                Rows::reference($row['member']),
                Rows::text($row['activity']),
                Rows::integer($row['value']),
                Rows::date($row['occurred_at']),
            ),
            $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s %s %s', self::COLUMNS, QuestsTables::CONTRIBUTIONS, $where, self::ORDER), $parameters, $types),
        );
    }
}
