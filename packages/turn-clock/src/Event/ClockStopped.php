<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Event;

/**
 * A clock was stopped, or every player ran out of time: it changes no more.
 */
final readonly class ClockStopped implements ClockEvent
{
    public function __construct(public string $clock)
    {
    }
}
