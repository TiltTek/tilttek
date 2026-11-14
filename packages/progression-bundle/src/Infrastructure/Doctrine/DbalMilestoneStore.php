<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Progression\ReachedMilestone;
use Tilttek\Progression\Store\MilestoneStore;

/**
 * The milestones reached in `tilttek_progression_milestone`, through a DBAL connection: one row per track, milestone,
 * subject and season, inserted by a statement that writes nothing when the row is already there, with `delivered` false
 * until the observer was told of it.
 */
final readonly class DbalMilestoneStore implements MilestoneStore
{
    private const string COLUMNS = 'track, milestone, subject, member, season, reached_at, activity';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function reach(ReachedMilestone $reached): bool
    {
        return 0 < $this->connection->executeStatement(
            Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (%s, delivered) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', ProgressionTables::MILESTONES, self::COLUMNS), 'subject, track, milestone, season'),
            [$reached->track, $reached->milestone, $reached->subject->toString(), $reached->member->toString(), $reached->season, Rows::utc($reached->reachedAt), $reached->activity, false],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, Types::DATETIME_IMMUTABLE, ParameterType::STRING, ParameterType::BOOLEAN],
        );
    }

    #[Override]
    public function undelivered(ResourceReference $subject): array
    {
        return $this->milestones('subject = ? AND delivered = ?', [$subject->toString(), false], [ParameterType::STRING, ParameterType::BOOLEAN]);
    }

    #[Override]
    public function markDelivered(ReachedMilestone $reached): void
    {
        $this->connection->executeStatement(
            \sprintf('UPDATE %s SET delivered = ? WHERE subject = ? AND track = ? AND milestone = ? AND season = ? AND delivered = ?', ProgressionTables::MILESTONES),
            [true, $reached->subject->toString(), $reached->track, $reached->milestone, $reached->season, false],
            [ParameterType::BOOLEAN, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::BOOLEAN],
        );
    }

    #[Override]
    public function of(ResourceReference $subject): array
    {
        return $this->milestones('subject = ?', [$subject->toString()]);
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return $this->milestones('subject = ? OR member = ?', [$member->toString(), $member->toString()]);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE subject = ? OR member = ?', ProgressionTables::MILESTONES), [$member->toString(), $member->toString()]));
    }

    /**
     * @param list<mixed>         $parameters
     * @param list<ParameterType> $types
     *
     * @return list<ReachedMilestone>
     */
    private function milestones(string $condition, array $parameters, array $types = []): array
    {
        return array_map(
            static fn (array $row): ReachedMilestone => new ReachedMilestone(
                Rows::text($row['track']),
                Rows::text($row['milestone']),
                ResourceReference::fromString(Rows::text($row['subject'])),
                ResourceReference::fromString(Rows::text($row['member'])),
                Rows::text($row['season']),
                Rows::date($row['reached_at']),
                Rows::text($row['activity']),
            ),
            $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE %s ORDER BY reached_at, track, milestone', self::COLUMNS, ProgressionTables::MILESTONES, $condition), $parameters, $types),
        );
    }
}
