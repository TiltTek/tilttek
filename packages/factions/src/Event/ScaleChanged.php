<?php

declare(strict_types=1);

namespace Tilttek\Factions\Event;

/**
 * The scale of a roster was changed.
 */
final readonly class ScaleChanged implements RosterEvent
{
    public function __construct(public string $campaign)
    {
    }
}
