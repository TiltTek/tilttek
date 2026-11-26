<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Event;

/**
 * A session took place.
 */
final readonly class SessionHeld implements SessionEvent
{
    public function __construct(public string $series, public string $session)
    {
    }
}
