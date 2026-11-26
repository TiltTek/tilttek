<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tiebreak;

use Override;
use Tilttek\Tournament\Contract\Tiebreaker;
use Tilttek\Tournament\Records;

/**
 * The head-to-head: the points each participant scored in the matches between the participants still tied, and those
 * alone. Computed again on each smaller group a later tie-breaker leaves tied.
 */
final readonly class HeadToHead implements Tiebreaker
{
    #[Override]
    public function id(): string
    {
        return 'head-to-head';
    }

    #[Override]
    public function scores(array $tied, Records $records): array
    {
        $inGroup = array_flip($tied);
        $scores = [];

        foreach ($tied as $participant) {
            $score = 0;

            foreach ($records->of($participant)->games as $game) {
                if (null !== $game->opponent && isset($inGroup[$game->opponent])) {
                    $score += $game->points;
                }
            }

            $scores[$participant] = $score;
        }

        return $scores;
    }
}
