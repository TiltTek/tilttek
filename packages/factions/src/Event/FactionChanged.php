<?php

declare(strict_types=1);

namespace Tilttek\Factions\Event;

/**
 * The name, the summary, the ranks or the secrecy of a faction were changed.
 */
final readonly class FactionChanged implements RosterEvent
{
    public function __construct(public string $campaign, public string $faction)
    {
    }
}
