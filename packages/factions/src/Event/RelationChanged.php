<?php

declare(strict_types=1);

namespace Tilttek\Factions\Event;

/**
 * The relation between two factions was set, changed or ended. `faction` and `other` are the pair, the lower key
 * first.
 */
final readonly class RelationChanged implements RosterEvent
{
    public function __construct(public string $campaign, public string $faction, public string $other)
    {
    }
}
