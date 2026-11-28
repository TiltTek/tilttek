<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Event;

/**
 * A fact of an entry, by identifiers only: an application sends a message from it, and reads the rest from the store.
 */
interface DowntimeEvent
{
    /** The identifier of the entry. */
    public string $entry { get; }

    /** The identifier of its campaign. */
    public string $campaign { get; }

    /** The identifier of its character. */
    public string $character { get; }
}
