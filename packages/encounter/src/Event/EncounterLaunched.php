<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Event;

/**
 * The game master launched the encounter: it was handed to the initiative tracker, and no longer changes.
 */
final readonly class EncounterLaunched implements EncounterEvent
{
    public function __construct(public string $encounter, public int $combatants)
    {
    }
}
