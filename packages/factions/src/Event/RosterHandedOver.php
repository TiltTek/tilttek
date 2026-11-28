<?php

declare(strict_types=1);

namespace Tilttek\Factions\Event;

/**
 * A roster was handed to another game master.
 */
final readonly class RosterHandedOver implements RosterEvent
{
    public function __construct(public string $campaign, public string $gameMaster)
    {
    }
}
