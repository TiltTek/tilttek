<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Event;

/**
 * An encounter was started, its deck shuffled; no card is dealt yet.
 */
final readonly class EncounterStarted implements InitiativeEvent
{
    public function __construct(public string $encounter)
    {
    }
}
