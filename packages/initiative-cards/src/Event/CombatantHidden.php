<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Event;

/**
 * A combatant was hidden from the players.
 */
final readonly class CombatantHidden implements CombatantEvent
{
    public function __construct(public string $encounter, public string $combatant)
    {
    }
}
