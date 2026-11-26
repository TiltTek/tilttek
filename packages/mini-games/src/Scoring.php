<?php

declare(strict_types=1);

namespace Tilttek\MiniGames;

/**
 * How a mini-game scores: which way its scores go, and the statistic of the seat its outcome reports the score in.
 */
final readonly class Scoring
{
    /**
     * @param string $statistic the seat statistic, declared by the game's definition, that holds the score
     */
    public function __construct(
        public ScoreOrder $order,
        public string $statistic = 'score',
    ) {
    }
}
