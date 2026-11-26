<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Event;

/**
 * A session was planned: from the rule, or added, and then off calendar when it is not one of its occurrences.
 */
final readonly class SessionPlanned implements SessionEvent
{
    public function __construct(public string $series, public string $session, public bool $offCalendar)
    {
    }
}
