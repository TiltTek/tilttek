<?php

declare(strict_types=1);

namespace Tilttek\MiniGames;

/**
 * Which way a game's scores go: the most points, or the fewest guesses, moves or seconds. A game declares one, so that
 * the same board ranks a game of points and a game against the clock.
 */
enum ScoreOrder: string
{
    case HigherIsBetter = 'higher';
    case LowerIsBetter = 'lower';

    /**
     * Whether a score beats another, strictly: two equal scores beat neither.
     */
    public function isBetter(int $score, int $than): bool
    {
        return self::HigherIsBetter === $this ? $score > $than : $score < $than;
    }

    /**
     * Whether a score sets a new record: the first score always does, an equal score never does.
     */
    public function isNewRecord(int $score, ?int $record): bool
    {
        return null === $record || $this->isBetter($score, $record);
    }

    /**
     * The best of some scores, or null when there are none.
     *
     * @param list<int> $scores
     */
    public function best(array $scores): ?int
    {
        $best = null;

        foreach ($scores as $score) {
            if ($this->isNewRecord($score, $best)) {
                $best = $score;
            }
        }

        return $best;
    }
}
