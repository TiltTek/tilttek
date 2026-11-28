<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Event;

/**
 * A combatant's turn started; `hidden` when the players do not see the combatant.
 */
final readonly class TurnStarted implements CombatantEvent
{
    public function __construct(public string $encounter, public string $combatant, public bool $hidden)
    {
    }
}
