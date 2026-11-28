<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Event;

/**
 * The game master added a participant to the encounter.
 */
final readonly class ParticipantAdded implements EncounterEvent
{
    public function __construct(public string $encounter, public string $participant)
    {
    }
}
