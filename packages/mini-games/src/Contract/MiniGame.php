<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Contract;

use Tilttek\GameEngine\Contract\Game;
use Tilttek\GameEngine\GameState;
use Tilttek\MiniGames\Control;
use Tilttek\MiniGames\Scoring;
use Tilttek\MiniGames\ShareGrid;

/**
 * A mini-game: a game of `tilttek/game-engine`, played alone at its own table, that also says how it scores, which
 * keys play it, and how a result is shared without giving the game away.
 */
interface MiniGame extends Game
{
    /**
     * Which way its scores go, and the seat statistic its outcome reports the score in.
     */
    public function scoring(): Scoring;

    /**
     * Every action of the game and the keys that perform it: the game is played with the keyboard alone.
     *
     * @return non-empty-list<Control>
     */
    public function controls(): array;

    /**
     * The result of a table to share: how each step went, never what it was.
     */
    public function share(GameState $state): ShareGrid;
}
