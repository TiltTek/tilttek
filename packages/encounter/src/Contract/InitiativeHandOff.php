<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Contract;

use Tilttek\Encounter\Initiative\InitiativeRoster;

/**
 * Hands a launched encounter to an initiative tracker — a deck of initiative cards, a tracker of rolls — which runs its
 * rounds and turns from then on. The library depends on no tracker: the application writes the adapter.
 */
interface InitiativeHandOff
{
    /**
     * Starts the encounter in the tracker, with the combatants of the roster. Called once the launch is kept; a tracker
     * refuses an encounter it already runs, so that handing the same roster again is safe.
     */
    public function handOff(InitiativeRoster $roster): void;
}
