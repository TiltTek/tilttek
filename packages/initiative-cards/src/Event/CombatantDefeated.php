<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Event;

/**
 * A combatant was defeated: it stays in the order, faded, and its turns are skipped.
 */
final readonly class CombatantDefeated implements CombatantEvent
{
    public function __construct(public string $encounter, public string $combatant)
    {
    }
}
