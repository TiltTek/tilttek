<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Tests\Benchmark;

use Tilttek\CardDeck\Gesture\Shuffle;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Preferences;
use Tilttek\GameEngine\Randomness;
use Tilttek\GameEngine\TableOptions;
use Tilttek\GameEngine\Turn;
use Tilttek\GameEngine\TurnOrder;
use Tilttek\TavernGames\Game\CrazyEights;

/**
 * What each move and each view of Crazy Eights does: replay the gestures of the cards from their seed, then build a
 * seat's view. A long game holds 200 gestures; `CrazyEights::MAX_GESTURES` is the bound of a game.
 */
final readonly class CrazyEightsBench
{
    private CrazyEights $game;

    private GameState $long;

    private GameState $bound;

    public function __construct()
    {
        $this->game = new CrazyEights();
        $this->long = $this->state(200);
        $this->bound = $this->state(CrazyEights::MAX_GESTURES - 1);
    }

    public function benchViewAfterTwoHundredGestures(): void
    {
        $this->game->view($this->long, 'ann', Preferences::resolve([]));
    }

    public function benchViewAtTheBoundOfAGame(): void
    {
        $this->game->view($this->bound, 'ann', Preferences::resolve([]));
    }

    private function state(int $gestures): GameState
    {
        $seats = ['ann', 'bob', 'cid', 'dee'];
        $options = TableOptions::resolve($this->game->definition()->options);
        $state = new GameState('bench', CrazyEights::NAME, 0, $seats, $options, 'playing', Turn::start(TurnOrder::Free, $seats, []));
        $data = $this->game->setup($state, new Randomness(str_repeat('ab', 32), 0));
        $dealt = \is_array($data['gestures']) ? $data['gestures'] : [];
        $data['gestures'] = [...$dealt, ...array_fill(0, $gestures - \count($dealt), new Shuffle()->toArray())];

        return $state->withData($data);
    }
}
