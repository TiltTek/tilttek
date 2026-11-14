<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Event;

/**
 * Dispatched when the host calls a square, in a game whose squares are called: the application pushes it to every
 * player. It carries identifiers only.
 *
 * It is dispatched at least once: a listener is idempotent, keyed by `key()`.
 */
final readonly class SquareCalled
{
    public function __construct(
        public string $game,
        public string $square,
    ) {
    }

    /** The same each time this call is dispatched again: a square is called once in a game. */
    public function key(): string
    {
        return \sprintf('%s/called/%s', $this->game, $this->square);
    }
}
