<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

/**
 * What a publisher tells the players of a table after a move: identifiers and counters only, never the game's data,
 * which each seat reads through its own view.
 */
final readonly class TableUpdate
{
    /**
     * @param list<string> $toAct the seats that may act now
     */
    public function __construct(
        public string $gameId,
        public int $sequence,
        public string $phase,
        public int $round,
        public array $toAct,
        public bool $over,
    ) {
    }

    public static function of(GameState $state): self
    {
        return new self($state->gameId, $state->sequence, $state->phase, $state->turn->round, $state->isOver() ? [] : $state->turn->toAct(), $state->isOver());
    }
}
