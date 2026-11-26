<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Event;

/**
 * A fact of one player of a clock.
 */
interface PlayerEvent extends ClockEvent
{
    /** The identifier of the player. */
    public string $player { get; }
}
