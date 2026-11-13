<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Event;

/**
 * Dispatched once a card wins, once per card: the application announces the winner, gives the prize or plays a
 * celebration. It carries identifiers only: the game, the card's serial, its player's reference — null for a printed
 * card — and the name of the pattern it won with.
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
}
