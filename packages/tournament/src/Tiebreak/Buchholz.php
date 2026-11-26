<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tiebreak;

use Override;
use Tilttek\Tournament\Contract\Tiebreaker;
use Tilttek\Tournament\Exception\InvalidTournament;
use Tilttek\Tournament\Records;

/**
 * The Buchholz score: the sum of the points of every opponent the participant was paired with, played or forfeited. A
 * bye adds nothing. The cut Buchholz leaves out the lowest scores of the opponents, as many as asked, so that one weak
 * opponent drawn by chance weighs less.
 */
final readonly class Buchholz implements Tiebreaker
{
    /**
     * @param int $cut the lowest scores of the opponents left out, 0 to 4
     *
     * @throws InvalidTournament when the cut is out of range
     */
    public function __construct(
        private int $cut = 0,
    ) {
        if ($cut < 0 || $cut > 4) {
            throw InvalidTournament::because('A cut Buchholz leaves out 0 to 4 opponents.');
        }
    }

    #[Override]
    public function id(): string
    {
        return 0 === $this->cut ? 'buchholz' : 'buchholz-cut-'.$this->cut;
    }

    #[Override]
    public function scores(array $tied, Records $records): array
    {
        $scores = [];

        foreach ($tied as $participant) {
            $points = array_map($records->points(...), $records->of($participant)->opponents());
            sort($points);
            $scores[$participant] = array_sum(\array_slice($points, $this->cut));
        }

        return $scores;
    }
}
