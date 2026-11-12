<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\Circle;
use Tilttek\Leaderboards\Contribution;
use Tilttek\Leaderboards\Ranking;
use Tilttek\Leaderboards\Score;

/**
 * Where the scores are kept up to date: the contribution of each activity to each board, written once even when the
 * journal hands the activity twice, and the score of each member on each board and period, read in rank order without
 * being counted again.
 *
 * A ranking orders scores as the board's `Ranking` says: from the highest down; among equal scores, by each tie-break
 * in its direction; then the first reached comes first, and among those reached at the same moment, the member written
 * first.
 */
interface ScoreStore
{
    /**
     * Keeps the contribution; false when the activity already brought one to this board.
     */
    public function add(Contribution $contribution): bool;

    /**
     * Removes every contribution of the activity, for its cancellation, and returns them.
     *
     * @return list<Contribution>
     */
    public function remove(string $activity): array;

    /**
     * The contributions to the member's score on the board in the period, in the order they occurred.
     *
     * @return list<Contribution>
     */
    public function contributions(string $board, string $period, ResourceReference $member): array;

    /**
     * Forgets the contributions of the activities from `since` on — all of them when `since` is null —, before a
     * rebuild, and returns them; the scores they made are left for the caller to count again.
     *
     * @return list<Contribution>
     */
    public function forget(?DateTimeImmutable $since): array;

    /**
     * Removes the contributions to the board — every key of a board ranked by object — that occurred before the moment,
     * as a sliding window moves on, and returns them; the scores they made are left for the caller to count again.
     *
     * @return list<Contribution>
     */
    public function expire(string $board, DateTimeImmutable $before): array;

    /**
     * Keeps the score, in place of the member's score on that board and period.
     */
    public function save(Score $score): void;

    public function delete(string $board, string $period, ResourceReference $member): void;

    public function score(string $board, string $period, ResourceReference $member): ?Score;

    /**
     * The first scores of the board in the period, in rank order — at most `limit`, at least 1 —, among the circle's
     * members when one is given.
     *
     * @return list<Score>
     */
    public function top(string $board, string $period, int $limit, Ranking $ranking, ?Circle $among = null): array;

    /**
     * How many scores of the score's board and period rank before it, as `Ranking::ranksBefore()` says.
     */
    public function ahead(Score $score, Ranking $ranking, ?Circle $among = null): int;

    /**
     * How many members have a score on the board in the period, among the circle's members when one is given.
     */
    public function participants(string $board, string $period, ?Circle $among = null): int;

    /**
     * The member's scores, by board and period, for an export.
     *
     * @return list<Score>
     */
    public function of(ResourceReference $member): array;

    /**
     * Removes the member's contributions and scores.
     *
     * @return int how many rows were removed
     */
    public function eraseMember(ResourceReference $member): int;
}
