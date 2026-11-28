<?php

declare(strict_types=1);

namespace Tilttek\Factions\Event;

/**
 * A faction was added to a roster.
 */
final readonly class FactionAdded implements RosterEvent
{
    public function __construct(public string $campaign, public string $faction)
    {
    }
}
