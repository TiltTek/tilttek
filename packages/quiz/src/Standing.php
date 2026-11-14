<?php

declare(strict_types=1);

namespace Tilttek\Quiz;

/**
 * A player's place in the standings of a live session: their rank, their score, how many answers were fully right,
 * and the milliseconds their scoring answers took, which break a tie of scores.
 */
final readonly class Standing
{
    public function __construct(
        public int $rank,
        public string $player,
        public int $score,
        public int $right,
        public int $time,
    ) {
    }
}
