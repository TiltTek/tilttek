<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

/**
 * A move as the log keeps it: its place in the log, from 1, the move, and whether it drew at random — a move that drew
 * revealed something, and is not taken back.
 */
final readonly class LoggedMove
{
    public function __construct(
        public int $sequence,
        public Move $move,
        public bool $drew = false,
    ) {
    }
}
