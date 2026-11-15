<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Contract\LeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Lead;

/**
 * The leads taken in `tilttek_leaderboards_lead`, through a DBAL connection: a lead is inserted by a statement that
 * writes nothing when the member already took the lead of that board in that period, so that two processes keep it
 * once.
 */
final readonly class DbalLeadStore implements LeadStore
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function claim(Lead $lead): bool
    {
        return 0 < $this->connection->executeStatement(
            Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (board, period, member, taken_at) VALUES (?, ?, ?, ?)', LeaderboardsTables::LEADS), 'board, period, member'),
            [$lead->board, $lead->period, $lead->member->toString(), $lead->takenAt],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, Types::DATETIME_UTC_IMMUTABLE],
        );
    }

    #[Override]
    public function has(string $board, string $period, ResourceReference $member): bool
    {
        return false !== $this->connection->fetchOne(\sprintf('SELECT 1 FROM %s WHERE board = ? AND period = ? AND member = ?', LeaderboardsTables::LEADS), [$board, $period, $member->toString()]);
    }

    #[Override]
    public function leads(ResourceReference $member): array
    {
        $leads = [];

        foreach ($this->connection->fetchAllAssociative(\sprintf('SELECT board, period, taken_at FROM %s WHERE member = ? ORDER BY board, period', LeaderboardsTables::LEADS), [$member->toString()]) as $row) {
            $leads[] = new Lead(Rows::text($row['board']), Rows::text($row['period']), $member, Rows::date($row['taken_at']));
        }

        return $leads;
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', LeaderboardsTables::LEADS), [$member->toString()]));
    }
}
