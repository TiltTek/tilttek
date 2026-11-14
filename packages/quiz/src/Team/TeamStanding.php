<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Team;

/**
 * A team's place in the standings of a live session: its rank, its score, how many answers of its players were fully
 * right, the milliseconds of their scoring answers, which break a tie of scores, and how many players it has.
 */
final readonly class TeamStanding
{
    public function __construct(
        public int $rank,
        public string $team,
        public int $score,
        public int $right,
        public int $time,
        public int $players,
    ) {
    }
}
