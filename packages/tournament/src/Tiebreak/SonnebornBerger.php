<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tiebreak;

use Override;
use Tilttek\Tournament\Contract\Tiebreaker;
use Tilttek\Tournament\Records;

/**
 * The Sonneborn-Berger score: for each match against an opponent, the opponent's points times the points the
 * participant scored in it. A win against a strong opponent weighs most, a draw half as much with a draw worth half a
 * win; a loss and a bye add nothing. With the points of the scoring in whole numbers, the score is the classic one
 * times the points of a win, the same order.
 */
final readonly class SonnebornBerger implements Tiebreaker
{
    #[Override]
    public function id(): string
    {
        return 'sonneborn-berger';
    }

    #[Override]
    public function scores(array $tied, Records $records): array
    {
        $scores = [];

        foreach ($tied as $participant) {
            $score = 0;

            foreach ($records->of($participant)->games as $game) {
                if (null !== $game->opponent) {
                    $score += $records->points($game->opponent) * $game->points;
                }
            }

            $scores[$participant] = $score;
        }

        return $scores;
    }
}
