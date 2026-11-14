<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Event;

/**
 * Dispatched when a gesture ticks a square of a card, in a game ticked by activity: the application pushes the card
 * again. It carries identifiers only: the game, the card's serial, the square and the idempotency key of the gesture
 * that ticked it.
 *
 * It is dispatched at least once: a listener is idempotent, keyed by `key()`.
 */
final readonly class SquareTicked
{
    public function __construct(
        public string $game,
        public int $serial,
        public string $square,
        public string $gesture,
    ) {
    }

    /** The same each time this tick is dispatched again, and another for any other tick. */
    public function key(): string
    {
        return \sprintf('%s/%d/ticked/%s/%s', $this->game, $this->serial, $this->square, $this->gesture);
    }
}
