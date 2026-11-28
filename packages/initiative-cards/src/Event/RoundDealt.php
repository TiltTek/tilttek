<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Event;

/**
 * A round was dealt: every combatant still standing holds a card, and the turn order follows the cards.
 */
final readonly class RoundDealt implements InitiativeEvent
{
    public function __construct(public string $encounter, public int $round)
    {
    }
}
