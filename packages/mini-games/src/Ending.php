<?php

declare(strict_types=1);

namespace Tilttek\MiniGames;

use Tilttek\GameEngine\Outcome;

/**
 * The end of a game as one player lives it: its tone, the score the game reports — none when the game reports none for
 * this ending —, and whether that score is a new record for them, which an equal score never is.
 */
final readonly class Ending
{
    public function __construct(
        public Tone $tone,
        public ?int $score,
        public bool $record,
    ) {
    }

    /**
     * The ending of a seat, from the outcome of its table.
     *
     * @param ?int $best the best score the player had before this game, or null for a first game
     */
    public static function of(Outcome $outcome, string $seat, Scoring $scoring, ?int $best = null): self
    {
        $score = $outcome->statistics->ofSeat($seat, $scoring->statistic);
        $tone = \in_array($seat, $outcome->winners, true) ? Tone::Victory : Tone::Completed;

        return new self($tone, $score, null !== $score && $scoring->order->isNewRecord($score, $best));
    }
}
