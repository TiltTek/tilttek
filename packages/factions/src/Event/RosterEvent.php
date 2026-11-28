<?php

declare(strict_types=1);

namespace Tilttek\Factions\Event;

/**
 * A fact of a roster, by identifiers only: an application sends a message from it, and reads the rest from the store.
 */
interface RosterEvent
{
    /** The identifier of the campaign. */
    public string $campaign { get; }
}
