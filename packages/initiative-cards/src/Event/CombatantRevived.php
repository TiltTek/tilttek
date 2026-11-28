<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Event;

/**
 * A defeated combatant stands again.
 */
final readonly class CombatantRevived implements CombatantEvent
{
    public function __construct(public string $encounter, public string $combatant)
    {
    }
}
