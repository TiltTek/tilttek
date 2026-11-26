<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Event;

/**
 * A player's budget ran out: in overtime, or out of time when the clock forfeits.
 */
final readonly class TimeExpired implements PlayerEvent
{
    public function __construct(public string $clock, public string $player)
    {
    }
}
