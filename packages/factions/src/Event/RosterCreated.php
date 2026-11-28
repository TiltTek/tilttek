<?php

declare(strict_types=1);

namespace Tilttek\Factions\Event;

/**
 * A roster was created for a campaign.
 */
final readonly class RosterCreated implements RosterEvent
{
    public function __construct(public string $campaign)
    {
    }
}
