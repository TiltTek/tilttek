<?php

declare(strict_types=1);

namespace Tilttek\MiniGames;

/**
 * The result of one player's daily game: the game, the day, the player's identifier, whether they won, and the score
 * the game reported — none when it reports none for that ending —, with the way scores go.
 */
final readonly class DailyScore
{
    public function __construct(
        public string $game,
        public Day $day,
        public string $player,
        public bool $won,
        public ?int $score,
        public ScoreOrder $order,
    ) {
    }
}
