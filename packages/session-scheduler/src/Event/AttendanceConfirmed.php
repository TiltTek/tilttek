<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Event;

/**
 * A member said they are coming to a session.
 */
final readonly class AttendanceConfirmed implements SessionEvent
{
    public function __construct(public string $series, public string $session, public string $member)
    {
    }
}
