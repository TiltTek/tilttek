<?php

declare(strict_types=1);

namespace Tilttek\TavernGames;

/**
 * How the end of a game is told to a seat: a victory, alone or shared, is celebrated; any other ending is told kindly,
 * never mocked.
 */
enum Tone: string
{
    /** The seat won alone. */
    case Victory = 'victory';

    /** The seat shares the win with others. */
    case Tie = 'tie';

    /** The seat did not win, or nobody did: a kind word and an invitation to play again. */
    case GoodGame = 'good-game';

    /**
     * Whether the application plays a celebration: a victory, alone or shared.
     */
    public function celebrates(): bool
    {
        return self::GoodGame !== $this;
    }
}
