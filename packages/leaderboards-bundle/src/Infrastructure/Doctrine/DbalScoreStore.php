<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\Board;
use Tilttek\Leaderboards\Circle;
use Tilttek\Leaderboards\Contribution;
use Tilttek\Leaderboards\Direction;
use Tilttek\Leaderboards\Measure;
use Tilttek\Leaderboards\Ranking;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Store\ScoreStore;

/**
 * The contributions and the scores in `tilttek_leaderboards_contribution` and `tilttek_leaderboards_score`, through a
 * DBAL connection. A contribution is inserted by a statement that writes nothing when the activity already brought
 * one to the board; a score is replaced in one transaction; the first scores are read through the index in rank order,
 * the tie-breaks in the directions the ranking gives.
 */
final readonly class DbalScoreStore implements ScoreStore
{
    private const string CONTRIBUTION_COLUMNS = 'board, activity, period, member, amount, occurred_at, tie_breaks, tie_break_1, tie_break_2';

    private const string SCORE_COLUMNS = 'board, period, member, score, reached_at, tie_breaks, tie_break_1, tie_break_2';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(Contribution $contribution): bool
    {
        return 0 < $this->connection->executeStatement(
            Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (%s) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', LeaderboardsTables::CONTRIBUTIONS, self::CONTRIBUTION_COLUMNS), 'board, activity'),
            [$contribution->board, $contribution->activity, $contribution->period, $contribution->member->toString(), $contribution->amount, $contribution->occurredAt, ...$this->tieBreaks($contribution->tieBreaks)],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, Types::DATETIME_UTC_IMMUTABLE, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER],
        );
    }

    #[Override]
    public function remove(string $activity): array
    {
        $removed = $this->contributionsWhere('activity = ?', [$activity]);
        $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE activity = ?', LeaderboardsTables::CONTRIBUTIONS), [$activity]);

        return $removed;
    }

    #[Override]
    public function contributions(string $board, string $period, ResourceReference $member): array
    {
        return $this->contributionsWhere('board = ? AND period = ? AND member = ?', [$board, $period, $member->toString()]);
    }

    #[Override]
    public function forget(?DateTimeImmutable $since): array
    {
        if (!$since instanceof DateTimeImmutable) {
            $forgotten = $this->contributionsWhere('1 = 1', []);
            $this->connection->executeStatement(\sprintf('DELETE FROM %s', LeaderboardsTables::CONTRIBUTIONS));

            return $forgotten;
        }

        $forgotten = $this->contributionsWhere('occurred_at >= ?', [$since], [Types::DATETIME_UTC_IMMUTABLE]);
        $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE occurred_at >= ?', LeaderboardsTables::CONTRIBUTIONS), [$since], [Types::DATETIME_UTC_IMMUTABLE]);

        return $forgotten;
    }

    #[Override]
    public function expire(string $board, DateTimeImmutable $before): array
    {
        $condition = "(board = ? OR board LIKE ? ESCAPE '!') AND occurred_at < ?";
        $parameters = [$board, addcslashes($board, '!%_').Board::OBJECT_SEPARATOR.'%', $before];
        $types = [ParameterType::STRING, ParameterType::STRING, Types::DATETIME_UTC_IMMUTABLE];
        $expired = $this->contributionsWhere($condition, $parameters, $types);
        $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE %s', LeaderboardsTables::CONTRIBUTIONS, $condition), $parameters, $types);

        return $expired;
    }

    #[Override]
    public function save(Score $score): void
    {
        $this->connection->transactional(function (Connection $connection) use ($score): void {
            $this->delete($score->board, $score->period, $score->member);
            $connection->executeStatement(
                \sprintf('INSERT INTO %s (%s) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', LeaderboardsTables::SCORES, self::SCORE_COLUMNS),
                [$score->board, $score->period, $score->member->toString(), $score->value, $score->reachedAt, ...$this->tieBreaks($score->tieBreaks)],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, Types::DATETIME_UTC_IMMUTABLE, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER],
            );
        });
    }

    /**
     * Inserts the score when the member holds none, by a statement that writes nothing when the row is there, or else
     * updates it by a statement whose condition is the comparison: `reached_at <= ?` and other values on `latest`, a
     * better value or tie-break otherwise. Of two reports at the same time, the database writes only the one that
     * still supersedes the score held. Moments are kept to the second: on `latest`, two reports within the same second
     * compare as reached at the same moment.
     */
    #[Override]
    public function keep(Score $score, Measure $measure, Ranking $ranking): bool
    {
        $values = [$score->board, $score->period, $score->member->toString(), $score->value, $score->reachedAt, ...$this->tieBreaks($score->tieBreaks)];
        $types = [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, Types::DATETIME_UTC_IMMUTABLE, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER];

        if (0 < $this->connection->executeStatement(Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (%s) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', LeaderboardsTables::SCORES, self::SCORE_COLUMNS), 'board, period, member'), $values, $types)) {
            return true;
        }

        // The values of the condition, integers, follow the typed parameters: the database reads them as its columns.
        [$condition, $values] = Measure::Latest === $measure ? $this->same($score, $ranking) : $this->better($score, $ranking);
        $latest = Measure::Latest === $measure;

        return 1 === $this->connection->executeStatement(
            \sprintf('UPDATE %s SET score = ?, reached_at = ?, tie_breaks = ?, tie_break_1 = ?, tie_break_2 = ? WHERE board = ? AND period = ? AND member = ? AND %s', LeaderboardsTables::SCORES, $latest ? 'reached_at <= ? AND NOT ('.$condition.')' : $condition),
            [$score->value, $score->reachedAt, ...$this->tieBreaks($score->tieBreaks), $score->board, $score->period, $score->member->toString(), ...($latest ? [$score->reachedAt] : []), ...$values],
            [ParameterType::INTEGER, Types::DATETIME_UTC_IMMUTABLE, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ...($latest ? [Types::DATETIME_UTC_IMMUTABLE] : [])],
        );
    }

    #[Override]
    public function delete(string $board, string $period, ResourceReference $member): void
    {
        $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE board = ? AND period = ? AND member = ?', LeaderboardsTables::SCORES), [$board, $period, $member->toString()]);
    }

    #[Override]
    public function score(string $board, string $period, ResourceReference $member): ?Score
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT %s FROM %s WHERE board = ? AND period = ? AND member = ?', self::SCORE_COLUMNS, LeaderboardsTables::SCORES), [$board, $period, $member->toString()]);

        return false === $row ? null : $this->row($row);
    }

    #[Override]
    public function top(string $board, string $period, int $limit, Ranking $ranking, ?Circle $among = null): array
    {
        $query = $this->scores($board, $period, $among)->select(...explode(', ', self::SCORE_COLUMNS))->orderBy('score', 'DESC');

        foreach ($ranking->tieBreaks as $index => $direction) {
            $query->addOrderBy('tie_break_'.($index + 1), Direction::Descending === $direction ? 'DESC' : 'ASC');
        }

        return array_map(
            $this->row(...),
            $query->addOrderBy('reached_at')->addOrderBy('member')->setMaxResults($limit)->executeQuery()->fetchAllAssociative(),
        );
    }

    #[Override]
    public function ahead(Score $score, Ranking $ranking, ?Circle $among = null): int
    {
        $query = $this->scores($score->board, $score->period, $among)->select('COUNT(*)');
        $equal = [];
        $before = [];
        $columns = [['score', $score->value, Direction::Descending]];

        foreach ($ranking->tieBreaks as $index => $direction) {
            $columns[] = ['tie_break_'.($index + 1), $score->tieBreaks[$index] ?? 0, $direction];
        }

        foreach ($columns as [$column, $value, $direction]) {
            $before[] = '('.implode(' AND ', [...$equal, \sprintf('%s %s %s', $column, Direction::Descending === $direction ? '>' : '<', $query->createNamedParameter($value, ParameterType::INTEGER))]).')';
            $equal[] = \sprintf('%s = %s', $column, $query->createNamedParameter($value, ParameterType::INTEGER));
        }

        if ($ranking->byArrival()) {
            $reached = $query->createNamedParameter($score->reachedAt, Types::DATETIME_UTC_IMMUTABLE);
            $member = $query->createNamedParameter($score->member->toString());
            $before[] = \sprintf('(%s AND (reached_at < %s OR (reached_at = %s AND member < %s)))', implode(' AND ', $equal), $reached, $reached, $member);
        }

        return Rows::integer($query->andWhere('('.implode(' OR ', $before).')')->fetchOne());
    }

    #[Override]
    public function participants(string $board, string $period, ?Circle $among = null): int
    {
        return Rows::integer($this->scores($board, $period, $among)->select('COUNT(*)')->fetchOne());
    }

    #[Override]
    public function of(ResourceReference $member): array
    {
        return array_map(
            $this->row(...),
            $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE member = ? ORDER BY board, period', self::SCORE_COLUMNS, LeaderboardsTables::SCORES), [$member->toString()]),
        );
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', LeaderboardsTables::CONTRIBUTIONS), [$member->toString()]))
            + Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', LeaderboardsTables::SCORES), [$member->toString()]));
    }

    /**
     * The scores of the board in the period, among the circle's members when one is given, for a query to finish.
     */
    private function scores(string $board, string $period, ?Circle $among): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()->from(LeaderboardsTables::SCORES);
        $query->where('board = '.$query->createNamedParameter($board))->andWhere('period = '.$query->createNamedParameter($period));

        if ($among instanceof Circle) {
            $query->andWhere('member IN ('.$query->createNamedParameter($among->members, ArrayParameterType::STRING).')');
        }

        return $query;
    }

    /**
     * @param list<mixed>                $parameters
     * @param list<ParameterType|string> $types
     *
     * @return list<Contribution>
     */
    private function contributionsWhere(string $condition, array $parameters, array $types = []): array
    {
        return array_map(
            static fn (array $row): Contribution => new Contribution(
                Rows::text($row['board']),
                Rows::text($row['period']),
                ResourceReference::fromString(Rows::text($row['member'])),
                Rows::text($row['activity']),
                Rows::integer($row['amount']),
                Rows::date($row['occurred_at']),
                self::tieBreaksOf($row),
            ),
            $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE %s ORDER BY occurred_at, activity', self::CONTRIBUTION_COLUMNS, LeaderboardsTables::CONTRIBUTIONS, $condition), $parameters, $types),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function row(array $row): Score
    {
        return new Score(Rows::text($row['board']), Rows::text($row['period']), ResourceReference::fromString(Rows::text($row['member'])), Rows::integer($row['score']), Rows::date($row['reached_at']), self::tieBreaksOf($row));
    }

    /**
     * The condition of equal values: the score and each of the ranking's tie-breaks. A later report on `latest` is one
     * whose score held was reached at the same moment or before, and whose values are not these.
     *
     * @return array{string, list<int>}
     */
    private function same(Score $score, Ranking $ranking): array
    {
        $conditions = ['score = ?'];
        $parameters = [$score->value];

        foreach (array_keys($ranking->tieBreaks) as $index) {
            $conditions[] = \sprintf('tie_break_%d = ?', $index + 1);
            $parameters[] = $score->tieBreaks[$index] ?? 0;
        }

        return [implode(' AND ', $conditions), $parameters];
    }

    /**
     * The condition of a better report: a higher value, or an equal value and a better tie-break, each in its
     * direction, the first that differs deciding.
     *
     * @return array{string, list<int>}
     */
    private function better(Score $score, Ranking $ranking): array
    {
        $columns = [['score', '<', $score->value]];

        foreach ($ranking->tieBreaks as $index => $direction) {
            $columns[] = [\sprintf('tie_break_%d', $index + 1), Direction::Descending === $direction ? '<' : '>', $score->tieBreaks[$index] ?? 0];
        }

        $condition = '';
        $parameters = [];

        // Built from the last column: each wraps the condition of the columns after it.
        foreach (array_reverse($columns) as [$column, $operator, $value]) {
            if ('' === $condition) {
                $condition = \sprintf('%s %s ?', $column, $operator);
                $parameters = [$value];
            } else {
                $condition = \sprintf('(%s %s ? OR (%s = ? AND %s))', $column, $operator, $column, $condition);
                $parameters = [$value, $value, ...$parameters];
            }
        }

        return [$condition, $parameters];
    }

    /**
     * How many tie-breaks there are, and their two columns, 0 for a missing one.
     *
     * @param list<int> $tieBreaks
     *
     * @return array{int, int, int}
     */
    private function tieBreaks(array $tieBreaks): array
    {
        return [\count($tieBreaks), $tieBreaks[0] ?? 0, $tieBreaks[1] ?? 0];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<int>
     */
    private static function tieBreaksOf(array $row): array
    {
        return \array_slice([Rows::integer($row['tie_break_1']), Rows::integer($row['tie_break_2'])], 0, Rows::integer($row['tie_breaks']));
    }
}
