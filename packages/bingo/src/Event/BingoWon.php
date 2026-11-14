<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Event;

/**
 * Dispatched once a card wins: the application announces the winner, gives the prize or plays a celebration. It
 * carries identifiers only: the game, the card's serial, its player's reference — null for a printed card — and the
 * name of the pattern it won with.
 *
 * It is dispatched at least once: a listener is idempotent, keyed by `key()`.
 */
final readonly class BingoWon
{
    public function __construct(
        public string $game,
        public int $serial,
        public ?string $player,
        public string $pattern,
    ) {
    }

    /** The same each time this win is dispatched again: a card wins once. */
    public function key(): string
    {
        return \sprintf('%s/%d/won', $this->game, $this->serial);
    }
}
