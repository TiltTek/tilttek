<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\League\League;
use Tilttek\Leaderboards\League\Membership;
use Tilttek\Leaderboards\League\Movement;
use Tilttek\Leaderboards\League\Settlement;
use Tilttek\Leaderboards\Store\LeagueStore;
use UnexpectedValueException;

/**
 * The leagues in `tilttek_leaderboards_league_tier`, `tilttek_leaderboards_league_membership` and
 * `tilttek_leaderboards_league_week`, through a DBAL connection. A member is placed by a statement that writes nothing
 * when they already have a division that week, then read back — the membership they had, or the new one —; two members placed at the same moment may share the
 * last place of a division, which then holds one more. A week is settled in one transaction that begins by inserting
 * its row, so that a second settlement writes nothing.
 */
final readonly class DbalLeagueStore implements LeagueStore
{
    private const string MEMBERSHIP_COLUMNS = 'board, week, member, tier, division, joined_at, movement, cohort';

    private const string ORDER = 'ORDER BY tier, division, joined_at, member';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function tier(string $board, ResourceReference $member): ?string
    {
        $tier = $this->connection->fetchOne(\sprintf('SELECT tier FROM %s WHERE board = ? AND member = ?', LeaderboardsTables::LEAGUE_TIERS), [$board, $member->toString()]);

        return false === $tier ? null : Rows::text($tier);
    }

    #[Override]
    public function membership(string $board, string $week, ResourceReference $member): ?Membership
    {
        return $this->membershipsWhere('board = ? AND week = ? AND member = ?', [$board, $week, $member->toString()])[0] ?? null;
    }

    #[Override]
    public function join(string $board, string $week, ResourceReference $member, string $tier, DateTimeImmutable $at, int $size, int $cohort = League::NO_COHORT): Membership
    {
        $division = null;
        $highest = 0;

        foreach ($this->connection->fetchAllAssociative(
            \sprintf('SELECT division, MIN(cohort) AS cohort, COUNT(*) AS members FROM %s WHERE board = ? AND week = ? AND tier = ? GROUP BY division ORDER BY division', LeaderboardsTables::LEAGUE_MEMBERSHIPS),
            [$board, $week, $tier],
        ) as $row) {
            $highest = Rows::integer($row['division']);

            if (null === $division && Rows::integer($row['cohort']) === $cohort && Rows::integer($row['members']) < $size) {
                $division = $highest;
            }
        }

        $this->connection->executeStatement(
            Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (board, week, member, tier, division, cohort, joined_at) VALUES (?, ?, ?, ?, ?, ?, ?)', LeaderboardsTables::LEAGUE_MEMBERSHIPS), 'board, week, member'),
            [$board, $week, $member->toString(), $tier, $division ?? $highest + 1, $cohort, $at],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER, Types::DATETIME_UTC_IMMUTABLE],
        );

        return $this->membership($board, $week, $member) ?? throw new UnexpectedValueException(\sprintf('The membership of "%s" in the week "%s" of the board "%s" was not written.', $member->toString(), $week, $board));
    }

    #[Override]
    public function division(string $board, string $week, string $tier, int $division): array
    {
        return $this->membershipsWhere('board = ? AND week = ? AND tier = ? AND division = ?', [$board, $week, $tier, $division]);
    }

    #[Override]
    public function memberships(string $board, string $week): array
    {
        return $this->membershipsWhere('board = ? AND week = ?', [$board, $week]);
    }

    #[Override]
    public function isSettled(string $board, string $week): bool
    {
        return false !== $this->connection->fetchOne(\sprintf('SELECT 1 FROM %s WHERE board = ? AND week = ?', LeaderboardsTables::LEAGUE_WEEKS), [$board, $week]);
    }

    #[Override]
    public function unsettled(string $board): array
    {
        return array_map(
            Rows::text(...),
            $this->connection->fetchFirstColumn(
                \sprintf(
                    'SELECT DISTINCT m.week FROM %s m WHERE m.board = ? AND NOT EXISTS (SELECT 1 FROM %s w WHERE w.board = m.board AND w.week = m.week) ORDER BY m.week',
                    LeaderboardsTables::LEAGUE_MEMBERSHIPS,
                    LeaderboardsTables::LEAGUE_WEEKS,
                ),
                [$board],
            ),
        );
    }

    #[Override]
    public function settle(Settlement $settlement, array $tiers): bool
    {
        return $this->connection->transactional(static function (Connection $connection) use ($settlement, $tiers): bool {
            if (0 === $connection->executeStatement(
                Rows::ignoring($connection->getDatabasePlatform(), \sprintf('INTO %s (board, week, settled_at) VALUES (?, ?, ?)', LeaderboardsTables::LEAGUE_WEEKS), 'board, week'),
                [$settlement->board, $settlement->week, $settlement->settledAt],
                [ParameterType::STRING, ParameterType::STRING, Types::DATETIME_UTC_IMMUTABLE],
            )) {
                return false;
            }

            foreach ($settlement->memberships as $membership) {
                $connection->executeStatement(
                    \sprintf('UPDATE %s SET movement = ? WHERE board = ? AND week = ? AND member = ?', LeaderboardsTables::LEAGUE_MEMBERSHIPS),
                    [$membership->movement?->value, $membership->board, $membership->week, $membership->member->toString()],
                );
            }

            foreach ($tiers as $member => $tier) {
                $connection->executeStatement(\sprintf('DELETE FROM %s WHERE board = ? AND member = ?', LeaderboardsTables::LEAGUE_TIERS), [$settlement->board, $member]);
                $connection->insert(LeaderboardsTables::LEAGUE_TIERS, ['board' => $settlement->board, 'member' => $member, 'tier' => $tier]);
            }

            return true;
        });
    }

    #[Override]
    public function tiersOf(ResourceReference $member): array
    {
        $tiers = [];

        foreach ($this->connection->fetchAllAssociative(\sprintf('SELECT board, tier FROM %s WHERE member = ? ORDER BY board', LeaderboardsTables::LEAGUE_TIERS), [$member->toString()]) as $row) {
            $tiers[Rows::text($row['board'])] = Rows::text($row['tier']);
        }

        return $tiers;
    }

    #[Override]
    public function membershipsOf(ResourceReference $member): array
    {
        return $this->membershipsWhere('member = ?', [$member->toString()], 'ORDER BY board, week');
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', LeaderboardsTables::LEAGUE_TIERS), [$member->toString()]))
            + Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', LeaderboardsTables::LEAGUE_MEMBERSHIPS), [$member->toString()]));
    }

    /**
     * @param list<int|string> $parameters
     *
     * @return list<Membership>
     */
    private function membershipsWhere(string $condition, array $parameters, string $order = self::ORDER): array
    {
        return array_map(
            static fn (array $row): Membership => new Membership(
                Rows::text($row['board']),
                Rows::text($row['week']),
                ResourceReference::fromString(Rows::text($row['member'])),
                Rows::text($row['tier']),
                Rows::integer($row['division']),
                Rows::date($row['joined_at']),
                null === $row['movement'] ? null : Movement::from(Rows::text($row['movement'])),
                Rows::integer($row['cohort']),
            ),
            $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE %s %s', self::MEMBERSHIP_COLUMNS, LeaderboardsTables::LEAGUE_MEMBERSHIPS, $condition, $order), $parameters),
        );
    }
}
