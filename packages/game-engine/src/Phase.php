<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * A phase of a game — the exploration, the encounter, a round of bidding —, who acts during it, and how many moves end
 * a seat's turn.
 */
final readonly class Phase
{
    /**
     * @param string $name         the phase's name, `encounter`
     * @param ?int   $movesPerTurn the moves after which a seat's turn ends by itself, 1 to 100; only by `engine.end-turn`
     *                             when null
     *
     * @throws InvalidGame
     */
    public function __construct(
        public string $name,
        public TurnOrder $turnOrder = TurnOrder::Free,
        public ?int $movesPerTurn = null,
    ) {
        Name::check($name, 'a phase');

        if (null !== $movesPerTurn && ($movesPerTurn < 1 || $movesPerTurn > 100)) {
            throw InvalidGame::because(\sprintf('The phase "%s" ends a turn after 1 to 100 moves, not %d.', $name, $movesPerTurn));
        }

        if (null !== $movesPerTurn && TurnOrder::Free === $turnOrder) {
            throw InvalidGame::because(\sprintf('The phase "%s" has no turns: it cannot end one after %d moves.', $name, $movesPerTurn));
        }
    }
}
