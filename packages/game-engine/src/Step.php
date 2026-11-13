<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

/**
 * One move as the engine applies it: the move to append to the log, with its place and whether it drew at random, and
 * the state it leads to.
 */
final readonly class Step
{
    public function __construct(
        public LoggedMove $move,
        public GameState $state,
    ) {
    }
}
