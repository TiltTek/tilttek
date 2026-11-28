<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Event;

/**
 * A combatant joined the encounter; `hidden` when the players do not see it.
 */
final readonly class CombatantJoined implements CombatantEvent
{
    public function __construct(public string $encounter, public string $combatant, public bool $hidden)
    {
    }
}
