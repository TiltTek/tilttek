<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Event;

/**
 * A fact of a shelf, by identifiers only: an application sends a message from it, and reads the rest from the shelf,
 * as the shelf's visibility allows.
 */
interface ShelfEvent
{
    /** The identifier of the member whose shelf it is. */
    public string $member { get; }

    /** The identifier of the game in the catalogue. */
    public string $game { get; }
}
