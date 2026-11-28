<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Event;

/**
 * A fact of a night, by identifiers only: an application sends a message or a notification from it, and reads the
 * rest from the night.
 */
interface NightEvent
{
    /** The identifier of the night. */
    public string $night { get; }
}
