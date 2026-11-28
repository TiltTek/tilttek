<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Contract;

use Tilttek\GameEngine\Contract\Game;
use Tilttek\GameEngine\GameState;

/**
 * A tavern game: a turn-based game of `tilttek/game-engine` that keeps its own turns and accepts the timeout of a turn.
 * The timeout is the move `tavern.timeout` (`{seat}`), made by the table itself — no seat — for the seat playing; the
 * game ends that seat's turn the way its rules say, never with a penalty beyond the turn.
 */
interface TavernGame extends Game
{
    public const string TIMEOUT = 'tavern.timeout';

    /**
     * The seat whose turn it is, or null once the game is over.
     */
    public function toAct(GameState $state): ?string;
}
