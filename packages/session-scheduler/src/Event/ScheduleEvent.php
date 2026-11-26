<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Event;

/**
 * A fact of a series, by identifiers only: an application sends a message from it, and reads the rest from the store.
 */
interface ScheduleEvent
{
    /** The identifier of the series. */
    public string $series { get; }
}
