<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Event;

/**
 * A fact of a play, by identifiers only: an application sends a message from it, and reads the rest from the log.
 */
interface PlayEvent
{
    /** The identifier of the play. */
    public string $play { get; }

    /** The identifier of the game in the catalogue. */
    public string $game { get; }
}
