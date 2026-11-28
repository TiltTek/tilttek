<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Contract;

use Random\Randomizer;
use Tilttek\TavernGames\Odds\Trial;

/**
 * One position of a game, played to its end at random as many times as an estimate asks.
 */
interface Simulation
{
    /**
     * Plays the position to its end once, with the chance it is given.
     */
    public function trial(Randomizer $random): Trial;
}
