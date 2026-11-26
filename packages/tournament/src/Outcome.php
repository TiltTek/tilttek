<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

/**
 * How a match ended, from the side of the participant who played first.
 */
enum Outcome: string
{
    case FirstWins = 'first-wins';
    case SecondWins = 'second-wins';
    case Draw = 'draw';
    /** The first participant did not play: the second wins by forfeit. */
    case FirstForfeits = 'first-forfeits';
    /** The second participant did not play: the first wins by forfeit. */
    case SecondForfeits = 'second-forfeits';
    /** Neither played: both lose. */
    case BothForfeit = 'both-forfeit';

    /**
     * Whether the match was played over the board, and not decided by a forfeit.
     */
    public function isPlayed(): bool
    {
        return match ($this) {
            self::FirstWins, self::SecondWins, self::Draw => true,
            default => false,
        };
    }

    /**
     * Whether the first participant won, played or by forfeit.
     */
    public function firstWins(): bool
    {
        return self::FirstWins === $this || self::SecondForfeits === $this;
    }

    /**
     * Whether the second participant won, played or by forfeit.
     */
    public function secondWins(): bool
    {
        return self::SecondWins === $this || self::FirstForfeits === $this;
    }
}
