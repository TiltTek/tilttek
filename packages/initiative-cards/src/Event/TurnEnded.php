<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Event;

/**
 * A combatant's turn ended.
 */
final readonly class TurnEnded implements CombatantEvent
{
    public function __construct(public string $encounter, public string $combatant)
    {
    }
}
