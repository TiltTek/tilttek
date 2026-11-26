<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Event;

/**
 * A clock was paused: no time counts until it resumes.
 */
final readonly class ClockPaused implements ClockEvent
{
    public function __construct(public string $clock)
    {
    }
}
