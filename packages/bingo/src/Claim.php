<?php

declare(strict_types=1);

namespace Tilttek\Bingo;

/**
 * What the server found on a card: the cells ticked, in the game's own terms, and the pattern they win with, if any.
 */
final readonly class Claim
{
    /**
     * @param list<int> $ticked the cells ticked, the free centre included
     */
    public function __construct(
        public string $game,
        public int $serial,
        public array $ticked,
        public ?string $pattern,
    ) {
    }

    public function isWon(): bool
    {
        return null !== $this->pattern;
    }
}
