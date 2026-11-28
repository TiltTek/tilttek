<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Event;

/**
 * A hidden combatant was revealed to the players.
 */
final readonly class CombatantRevealed implements CombatantEvent
{
    public function __construct(public string $encounter, public string $combatant)
    {
    }
}
