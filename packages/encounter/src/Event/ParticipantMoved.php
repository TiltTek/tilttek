<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Event;

/**
 * The game master moved a participant elsewhere in the order of the encounter.
 */
final readonly class ParticipantMoved implements EncounterEvent
{
    public function __construct(public string $encounter, public string $participant)
    {
    }
}
