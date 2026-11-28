<?php

declare(strict_types=1);

namespace Tilttek\Factions\Event;

/**
 * A roster was deleted, with its history.
 */
final readonly class RosterDeleted implements RosterEvent
{
    public function __construct(public string $campaign)
    {
    }
}
