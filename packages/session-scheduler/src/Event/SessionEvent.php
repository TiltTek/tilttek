<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Event;

/**
 * A fact of one session of a series.
 */
interface SessionEvent extends ScheduleEvent
{
    /** The identifier of the session. */
    public string $session { get; }
}
