<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Contract;

use Tilttek\MiniGames\DailyScore;

/**
 * Where the score of each finished daily game goes: a leaderboard, an activity journal, the player's history. It is
 * told once per game, when the move that ends it is played, and carries identifiers and numbers only.
 */
interface ScoreSink
{
    public function record(DailyScore $score): void;
}
