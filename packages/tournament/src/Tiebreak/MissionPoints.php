<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tiebreak;

use Override;
use Tilttek\Tournament\Contract\Tiebreaker;
use Tilttek\Tournament\Records;

/**
 * The sum of the mission points the participant scored, as miniatures games count them beside the win.
 */
final readonly class MissionPoints implements Tiebreaker
{
    #[Override]
    public function id(): string
    {
        return 'mission-points';
    }

    #[Override]
    public function scores(array $tied, Records $records): array
    {
        $scores = [];

        foreach ($tied as $participant) {
            $scores[$participant] = $records->of($participant)->mission();
        }

        return $scores;
    }
}
