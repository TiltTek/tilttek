<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Event;

/**
 * The night was cancelled: the moment to tell the members signed up, once.
 */
final readonly class NightCancelled implements NightEvent
{
    public function __construct(public string $night)
    {
    }
}
