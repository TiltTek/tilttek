<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Event;

/**
 * Dispatched when a gesture ticks a square of a card, in a game ticked by activity: the application pushes the card
 * again. It carries identifiers only.
 */
final readonly class SquareTicked
{
    public function __construct(
        public string $game,
        public int $serial,
        public string $square,
    ) {
    }
}
