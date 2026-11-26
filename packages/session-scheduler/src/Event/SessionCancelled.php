<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Event;

/**
 * A planned session was cancelled.
 */
final readonly class SessionCancelled implements SessionEvent
{
    public function __construct(public string $series, public string $session)
    {
    }
}
