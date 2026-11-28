<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Event;

/**
 * A fact of an encounter, by identifiers only: an application sends a message from it, and reads the rest from the
 * store.
 */
interface InitiativeEvent
{
    /** The identifier of the encounter. */
    public string $encounter { get; }
}
