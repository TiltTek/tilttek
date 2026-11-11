<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Progression\Experience\Gain;
use Tilttek\Progression\Store\ExperienceStore;
use Tilttek\Progression\Store\Total;

/**
 * The experience in `tilttek_progression_gain`, through a DBAL connection: one row per track, rule and activity,
 * inserted by a statement that writes nothing when the row is already there; the totals are sums, never fields.
 */
final readonly class DbalExperienceStore implements ExperienceStore
{
    private const string COLUMNS = 'track, rule_name, subject, member, activity, amount, occurred_at, season';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(Gain $gain): bool
    {
        return 0 < $this->connection->executeStatement(
            Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (%s) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', ProgressionTables::GAINS, self::COLUMNS), 'track, rule_name, activity'),
            [$gain->track, $gain->rule, $gain->subject->toString(), $gain->member->toString(), $gain->activity, $gain->amount, Rows::utc($gain->occurredAt), $gain->season],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, Types::DATETIME_IMMUTABLE, ParameterType::STRING],
        );
    }

    #[Override]
    public function remove(string $activity): array
    {
        return $this->connection->transactional(function (Connection $connection) use ($activity): array {
            $removed = $this->gains(\sprintf('SELECT %s FROM %s WHERE activity = ? ORDER BY track, rule_name', self::COLUMNS, ProgressionTables::GAINS), [$activity]);
            $connection->executeStatement(\sprintf('DELETE FROM %s WHERE activity = ?', ProgressionTables::GAINS), [$activity]);

            return $removed;
        });
    }

    #[Override]
    public function total(string $track, ResourceReference $subject, string $season): int
    {
        return Rows::integer($this->connection->fetchOne(\sprintf('SELECT COALESCE(SUM(amount), 0) FROM %s WHERE subject = ? AND track = ? AND season = ?', ProgressionTables::GAINS), [$subject->toString(), $track, $season]));
    }

    #[Override]
    public function lifetime(string $track, ResourceReference $subject): int
    {
        return Rows::integer($this->connection->fetchOne(\sprintf('SELECT COALESCE(SUM(amount), 0) FROM %s WHERE subject = ? AND track = ?', ProgressionTables::GAINS), [$subject->toString(), $track]));
    }

    #[Override]
    public function seasons(string $track, ResourceReference $subject): array
    {
        return $this->totals(\sprintf("SELECT track, subject, season, SUM(amount) AS experience FROM %s WHERE subject = ? AND track = ? AND season <> '' GROUP BY track, subject, season ORDER BY season", ProgressionTables::GAINS), [$subject->toString(), $track]);
    }

    #[Override]
    public function earned(string $track, string $rule, ResourceReference $subject, ?DateTimeImmutable $since, ?DateTimeImmutable $until): int
    {
        $query = $this->connection->createQueryBuilder()->select('COALESCE(SUM(amount), 0)')->from(ProgressionTables::GAINS);
        $query->where('subject = '.$query->createNamedParameter($subject->toString()))
            ->andWhere('track = '.$query->createNamedParameter($track))
            ->andWhere('rule_name = '.$query->createNamedParameter($rule));

        if ($since instanceof DateTimeImmutable) {
            $query->andWhere('occurred_at >= '.$query->createNamedParameter(Rows::utc($since), Types::DATETIME_IMMUTABLE));
        }

        if ($until instanceof DateTimeImmutable) {
            $query->andWhere('occurred_at < '.$query->createNamedParameter(Rows::utc($until), Types::DATETIME_IMMUTABLE));
        }

        return Rows::integer($query->fetchOne());
    }

    #[Override]
    public function totalsSince(?DateTimeImmutable $since): array
    {
        $sums = \sprintf('SELECT g.track, g.subject, g.season, SUM(g.amount) AS experience FROM %s g', ProgressionTables::GAINS);

        if (!$since instanceof DateTimeImmutable) {
            return $this->totals($sums.' GROUP BY g.track, g.subject, g.season');
        }

        return $this->totals(
            $sums.\sprintf(' WHERE EXISTS (SELECT 1 FROM %s r WHERE r.track = g.track AND r.subject = g.subject AND r.season = g.season AND r.occurred_at >= ?) GROUP BY g.track, g.subject, g.season', ProgressionTables::GAINS),
            [Rows::utc($since)],
            [Types::DATETIME_IMMUTABLE],
        );
    }

    #[Override]
    public function of(ResourceReference $subject): array
    {
        return $this->gains(\sprintf('SELECT %s FROM %s WHERE subject = ? ORDER BY occurred_at, track, rule_name', self::COLUMNS, ProgressionTables::GAINS), [$subject->toString()]);
    }

    #[Override]
    public function forget(?DateTimeImmutable $since): int
    {
        if (!$since instanceof DateTimeImmutable) {
            return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s', ProgressionTables::GAINS)));
        }

        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE occurred_at >= ?', ProgressionTables::GAINS), [Rows::utc($since)], [Types::DATETIME_IMMUTABLE]));
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE subject = ? OR member = ?', ProgressionTables::GAINS), [$member->toString(), $member->toString()]));
    }

    /**
     * @param list<mixed> $parameters
     *
     * @return list<Gain>
     */
    private function gains(string $sql, array $parameters): array
    {
        return array_map(
            static fn (array $row): Gain => new Gain(
                Rows::text($row['track']),
                Rows::text($row['rule_name']),
                ResourceReference::fromString(Rows::text($row['subject'])),
                ResourceReference::fromString(Rows::text($row['member'])),
                Rows::text($row['activity']),
                Rows::integer($row['amount']),
                Rows::date($row['occurred_at']),
                Rows::text($row['season']),
            ),
            $this->connection->fetchAllAssociative($sql, $parameters),
        );
    }

    /**
     * @param list<mixed>                $parameters
     * @param list<string|ParameterType> $types|ParameterType> $types
     *
     * @return list<Total>
     */
    private function totals(string $sql, array $parameters = [], array $types = []): array
    {
        return array_map(
            static fn (array $row): Total => new Total(Rows::text($row['track']), ResourceReference::fromString(Rows::text($row['subject'])), Rows::text($row['season']), Rows::integer($row['experience'])),
            $this->connection->fetchAllAssociative($sql, $parameters, $types),
        );
    }
}
