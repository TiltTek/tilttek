<?php

declare(strict_types=1);

namespace Tilttek\MiniGames;

/**
 * How the end of a game is told to a player: a victory is celebrated; any other ending is told kindly, never mocked.
 */
enum Tone: string
{
    /** The player won: the application plays a celebration. */
    case Victory = 'victory';

    /** The game is over without a win: a kind word, an invitation to play another day, no penalty. */
    case Completed = 'completed';

    public function celebrates(): bool
    {
        return self::Victory === $this;
    }
}
