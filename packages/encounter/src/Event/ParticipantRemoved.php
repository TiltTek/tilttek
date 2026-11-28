<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Event;

/**
 * The game master removed a participant from the encounter.
 */
final readonly class ParticipantRemoved implements EncounterEvent
{
    public function __construct(public string $encounter, public string $participant)
    {
    }
}
