<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Event;

/**
 * A player's turn started: their time runs.
 */
final readonly class TurnStarted implements PlayerEvent
{
    public function __construct(public string $clock, public string $player)
    {
    }
}
