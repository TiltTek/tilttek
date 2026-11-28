<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Event;

/**
 * A fact of one combatant of an encounter.
 */
interface CombatantEvent extends InitiativeEvent
{
    /** The identifier of the combatant. */
    public string $combatant { get; }
}
