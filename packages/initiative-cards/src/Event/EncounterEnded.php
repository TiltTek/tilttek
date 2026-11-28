<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Event;

/**
 * An encounter was ended: it changes no more.
 */
final readonly class EncounterEnded implements InitiativeEvent
{
    public function __construct(public string $encounter)
    {
    }
}
