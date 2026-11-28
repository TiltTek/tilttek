<?php

declare(strict_types=1);

namespace Tilttek\Factions\Event;

/**
 * Where a subject stands with a faction changed. `subject` is its key: "party", or "character:" and an identifier.
 */
final readonly class StandingChanged implements RosterEvent
{
    public function __construct(public string $campaign, public string $faction, public string $subject)
    {
    }
}
