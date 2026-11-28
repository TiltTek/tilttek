<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Tests\Unit;

use Tilttek\GameEngine\Contract\Game;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\TableOptions;
use Tilttek\GameEngine\Turn;
use Tilttek\GameEngine\TurnOrder;

/**
 * States of a tavern game built by hand, to call its pure methods directly.
 */
final class GameStates
{
    /**
     * @param list<string>         $seats
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public static function of(Game $game, array $seats, array $data, array $options = [], ?Outcome $outcome = null): GameState
    {
        $definition = $game->definition();

        return new GameState('t-1', $definition->game, 0, $seats, TableOptions::resolve($definition->options, $options), $definition->initial, Turn::start(TurnOrder::Free, $seats, []), [], $data, $outcome);
    }
}
