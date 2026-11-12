<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

/**
 * A line of a ranking of teams: the rank — shared by equal totals —, the team's name, how many of its members hold a
 * score, the sum of their scores, the roll of luck added when the board enables it — 0 otherwise —, and the total.
 */
final readonly class TeamRow
{
    public function __construct(
        public int $rank,
        public string $team,
        public int $scoring,
        public int $score,
        public int $luck,
        public int $total,
    ) {
    }
}
