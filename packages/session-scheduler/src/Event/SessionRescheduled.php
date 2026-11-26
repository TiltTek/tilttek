<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Event;

/**
 * A planned session moved to another start; the replies given for the old one were dropped.
 */
final readonly class SessionRescheduled implements SessionEvent
{
    public function __construct(public string $series, public string $session, public bool $offCalendar)
    {
    }
}
