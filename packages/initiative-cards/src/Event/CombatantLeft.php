<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Event;

/**
 * A combatant left the encounter, its card discarded.
 */
final readonly class CombatantLeft implements CombatantEvent
{
    public function __construct(public string $encounter, public string $combatant)
    {
    }
}
