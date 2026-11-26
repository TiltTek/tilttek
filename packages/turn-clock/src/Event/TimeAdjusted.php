<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Event;

/**
 * A player's time was changed by `delta` milliseconds, added or taken away.
 */
final readonly class TimeAdjusted implements PlayerEvent
{
    public function __construct(public string $clock, public string $player, public int $delta)
    {
    }
}
