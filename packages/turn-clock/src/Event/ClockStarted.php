<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Event;

/**
 * A clock was started: the first turn runs.
 */
final readonly class ClockStarted implements ClockEvent
{
    public function __construct(public string $clock)
    {
    }
}
