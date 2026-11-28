<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Event;

/**
 * The game master prepared a new encounter.
 */
final readonly class EncounterPrepared implements EncounterEvent
{
    public function __construct(public string $encounter, public string $gameMaster)
    {
    }
}
