<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Event;

/**
 * A paused clock runs again.
 */
final readonly class ClockResumed implements ClockEvent
{
    public function __construct(public string $clock)
    {
    }
}
