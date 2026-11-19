<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom;

use Tilttek\PuzzleBuilder\GuessResult;

/**
 * What a guess or a skip gave, and the game to store after it.
 */
final readonly class RoomGuess
{
    public function __construct(
        public GuessResult $result,
        public Game $game,
    ) {
    }

    public function escaped(): bool
    {
        return GameOutcome::Escaped === $this->game->outcome;
    }
}
