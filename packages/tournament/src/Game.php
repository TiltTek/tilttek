<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

/**
 * One match of a participant, as the standings read it: the round, the opponent — none for a bye or a rest — the side
 * the participant played, the points they scored, their mission points and how the match ended.
 */
final readonly class Game
{
    public function __construct(
        public int $round,
        public ?string $opponent,
        public bool $first,
        public int $points,
        public ?Outcome $outcome = null,
        public int $mission = 0,
        public bool $rest = false,
    ) {
    }

    /**
     * Whether the match was played over the board against an opponent.
     */
    public function isPlayed(): bool
    {
        return null !== $this->opponent && true === $this->outcome?->isPlayed();
    }

    /**
     * Whether the participant won, played or by forfeit; a bye is not a win.
     */
    public function isWin(): bool
    {
        return $this->outcome instanceof Outcome && ($this->first ? $this->outcome->firstWins() : $this->outcome->secondWins());
    }
}
