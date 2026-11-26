<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Event;

/**
 * A fact of a preparation, by identifiers only: an application sends a message from it, and reads the rest from the
 * store.
 */
interface PreparationEvent
{
    /** The identifier of the preparation. */
    public string $preparation { get; }
}
