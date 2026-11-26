<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Tests\Unit;

use Tilttek\GameEngine\Contract\Game;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\TableOptions;
use Tilttek\GameEngine\Turn;
use Tilttek\GameEngine\TurnOrder;

/**
 * States of a mini-game built by hand, to call its pure methods directly.
 */
final class GameStates
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public static function of(Game $game, array $data, array $options = [], ?Outcome $outcome = null): GameState
    {
        $definition = $game->definition();

        return new GameState('t-1', $definition->game, 0, ['ann'], TableOptions::resolve($definition->options, $options), $definition->initial, Turn::start(TurnOrder::Free, ['ann'], []), [], $data, $outcome);
    }
}
