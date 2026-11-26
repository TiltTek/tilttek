<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Standings;

/**
 * A participant's place in the standings: their rank, their points, the scores of the tie-breakers that were computed
 * for them, whether the seeded draw had the last word, and whether they withdrew.
 */
final readonly class Standing
{
    /**
     * @param array<string, int> $tiebreaks the score of each tie-breaker computed for the participant, by code, in the
     *                                      order declared; a tie-breaker not needed is left out
     */
    public function __construct(
        public string $participant,
        public int $rank,
        public int $points,
        public array $tiebreaks = [],
        public bool $drawn = false,
        public bool $withdrawn = false,
    ) {
    }
}
