<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Event;

/**
 * The game master gave the encounter another title, or took its title away.
 */
final readonly class EncounterRetitled implements EncounterEvent
{
    public function __construct(public string $encounter)
    {
    }
}
