<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Event;

/**
 * The game master changed a participant of the encounter: its rating, copies, controller, visibility, bonus or record.
 */
final readonly class ParticipantChanged implements EncounterEvent
{
    public function __construct(public string $encounter, public string $participant)
    {
    }
}
