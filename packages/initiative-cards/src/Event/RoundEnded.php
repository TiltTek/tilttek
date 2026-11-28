<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Event;

/**
 * Every combatant still standing has acted in the round: the game master deals the next one.
 */
final readonly class RoundEnded implements InitiativeEvent
{
    public function __construct(public string $encounter, public int $round)
    {
    }
}
