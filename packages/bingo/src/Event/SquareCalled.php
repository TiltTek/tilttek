<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Event;

/**
 * Dispatched when the host calls a square, in a game whose squares are called: the application pushes it to every
 * player. It carries identifiers only.
 */
final readonly class SquareCalled
{
    public function __construct(
        public string $game,
        public string $square,
    ) {
    }
}
