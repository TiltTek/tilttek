<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

/**
 * A table as the engine gives it back after a step: its log, the truth, and the state that log leads to.
 */
final readonly class Table
{
    public function __construct(
        public GameLog $log,
        public GameState $state,
    ) {
    }
}
