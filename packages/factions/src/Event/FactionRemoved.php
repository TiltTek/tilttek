<?php

declare(strict_types=1);

namespace Tilttek\Factions\Event;

/**
 * A faction was removed from a roster, with its relations.
 */
final readonly class FactionRemoved implements RosterEvent
{
    public function __construct(public string $campaign, public string $faction)
    {
    }
}
