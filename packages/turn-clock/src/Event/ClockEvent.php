<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Event;

/**
 * A fact of a clock, by identifiers only: an application sends a message from it, and reads the rest from the store.
 */
interface ClockEvent
{
    /** The identifier of the clock. */
    public string $clock { get; }
}
