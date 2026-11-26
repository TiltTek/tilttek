<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Elimination;

/**
 * Where a match of a bracket stands: who fills each place, as far as it is known, and who won. A place is a participant,
 * empty — a bye, or a match both sides lost — or not known yet, null, with `firstEmpty` and `secondEmpty` to tell the
 * two apart.
 */
final readonly class MatchState
{
    public function __construct(
        public BracketMatch $match,
        public ?string $first = null,
        public ?string $second = null,
        public bool $firstEmpty = false,
        public bool $secondEmpty = false,
        public ?string $winner = null,
        public bool $decided = false,
        public bool $walkover = false,
    ) {
    }

    /**
     * Whether both places are participants, so that the match is played.
     */
    public function isPlayable(): bool
    {
        return null !== $this->first && null !== $this->second;
    }

    /**
     * The participant who lost the match once it is decided; none for a walkover without an opponent.
     */
    public function loser(): ?string
    {
        if (!$this->decided || null === $this->winner) {
            return null;
        }

        return $this->winner === $this->first ? $this->second : $this->first;
    }
}
