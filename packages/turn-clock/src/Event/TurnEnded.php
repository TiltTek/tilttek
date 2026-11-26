<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Event;

/**
 * A player's turn ended, after `spent` milliseconds counted.
 */
final readonly class TurnEnded implements PlayerEvent
{
    public function __construct(public string $clock, public string $player, public int $spent)
    {
    }
}
