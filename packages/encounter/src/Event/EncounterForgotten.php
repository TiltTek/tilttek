<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Event;

/**
 * The game master removed the encounter from the store.
 */
final readonly class EncounterForgotten implements EncounterEvent
{
    public function __construct(public string $encounter)
    {
    }
}
