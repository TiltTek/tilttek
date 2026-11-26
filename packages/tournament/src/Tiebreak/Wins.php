<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tiebreak;

use Override;
use Tilttek\Tournament\Contract\Tiebreaker;
use Tilttek\Tournament\Records;

/**
 * The number of matches won over the board: byes and forfeit wins are left out.
 */
final readonly class Wins implements Tiebreaker
{
    #[Override]
    public function id(): string
    {
        return 'wins';
    }

    #[Override]
    public function scores(array $tied, Records $records): array
    {
        $scores = [];

        foreach ($tied as $participant) {
            $scores[$participant] = $records->of($participant)->wins();
        }

        return $scores;
    }
}
