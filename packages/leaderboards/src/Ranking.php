<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use Tilttek\Leaderboards\Exception\InvalidLeaderboard;

/**
 * How a board orders its scores: the highest score first; among equal scores, by each tie-break in turn, in its
 * direction; then the first to reach the score, or a shared rank.
 *
 * A listing always goes on to the moment the score was reached and to the member's reference, so that the order of
 * the lines never changes between two reads; with shared ranks, the lines equal on the score and every tie-break hold
 * the same rank.
 */
final readonly class Ranking
{
    public const int MAX_TIE_BREAKS = 2;

    /**
     * The digits of a value — a score or a tie-break, 0 to 10^15 — in a key: 16.
     */
    public const int VALUE_DIGITS = 16;

    /**
     * The digits of a moment in a key, in microseconds from the year 1: 18.
     */
    public const int MOMENT_DIGITS = 18;

    /**
     * The seconds from the year 1 to 1970, so that every moment counts from 0 in a key.
     */
    private const int EPOCH_OFFSET = 62_135_596_800;

    /**
     * @param list<Direction> $tieBreaks the direction of each tie-break, in turn — 0 to 2
     */
    public function __construct(
        public array $tieBreaks = [],
        public Ties $ties = Ties::FirstReached,
    ) {
        if (\count($tieBreaks) > self::MAX_TIE_BREAKS) {
            throw InvalidLeaderboard::because(\sprintf('A board orders a tie by %d measures at most; %d were given.', self::MAX_TIE_BREAKS, \count($tieBreaks)));
        }
    }

    /**
     * Whether the first to reach a score ranks first, each member on a rank of their own.
     */
    public function byArrival(): bool
    {
        return Ties::FirstReached === $this->ties;
    }

    /**
     * The order of two scores in a listing: negative when the first comes before the second.
     */
    public function compare(Score $first, Score $second): int
    {
        $order = $this->compareValues($first, $second);

        return 0 !== $order ? $order : [$first->reachedAt, $first->member->toString()] <=> [$second->reachedAt, $second->member->toString()];
    }

    /**
     * The order of two scores on the score and the tie-breaks alone: zero when they share a rank.
     */
    public function compareValues(Score $first, Score $second): int
    {
        $order = $second->value <=> $first->value;

        foreach ($this->tieBreaks as $index => $direction) {
            if (0 !== $order) {
                return $order;
            }

            $order = $direction->compare($first->tieBreaks[$index] ?? 0, $second->tieBreaks[$index] ?? 0);
        }

        return $order;
    }

    /**
     * Whether the other score ranks before the score: a better score or tie-break, or — when the first to reach a
     * score ranks first — an equal one that comes before it in a listing.
     */
    public function ranksBefore(Score $other, Score $score): bool
    {
        return ($this->byArrival() ? $this->compare($other, $score) : $this->compareValues($other, $score)) < 0;
    }

    /**
     * The key of the score that sorts, byte by byte, in listing order: its value from the highest, each tie-break in
     * its direction and the moment it was reached, each in a fixed number of digits, then the member's reference.
     */
    public function key(Score $score): string
    {
        return \sprintf('%s%018d%s', $this->valuesKey($score), ($score->reachedAt->getTimestamp() + self::EPOCH_OFFSET) * 1_000_000 + (int) $score->reachedAt->format('u'), $score->member->toString());
    }

    /**
     * The start of the key of the score: its value and its tie-breaks alone, equal for the scores that share a rank.
     */
    public function valuesKey(Score $score): string
    {
        $key = \sprintf('%016d', Score::MAX - $score->value);

        foreach ($this->tieBreaks as $index => $direction) {
            $value = $score->tieBreaks[$index] ?? 0;
            $key .= \sprintf('%016d', Direction::Descending === $direction ? Score::MAX - $value : $value);
        }

        return $key;
    }

    /**
     * The scores in listing order.
     *
     * @param array<Score> $scores
     *
     * @return list<Score>
     */
    public function sort(array $scores): array
    {
        $keyed = [];

        foreach ($scores as $score) {
            $keyed[$this->key($score)] = $score;
        }

        ksort($keyed, \SORT_STRING);

        return array_values($keyed);
    }

    /**
     * The scores, in rank order, with their ranks: one each when the first to reach a score ranks first, or the rank
     * of the score before when it is equal on the score and every tie-break and ranks are shared.
     *
     * @param list<Score> $scores in listing order
     *
     * @return list<array{rank: int, score: Score}>
     */
    public function rank(array $scores): array
    {
        $ranked = [];

        foreach ($scores as $index => $score) {
            $before = $ranked[$index - 1] ?? null;
            $ranked[] = ['rank' => !$this->byArrival() && null !== $before && 0 === $this->compareValues($before['score'], $score) ? $before['rank'] : $index + 1, 'score' => $score];
        }

        return $ranked;
    }
}
