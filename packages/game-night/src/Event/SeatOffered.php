<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Event;

/**
 * A member waiting holds their places now: the moment to tell them, once.
 */
final readonly class SeatOffered implements NightEvent
{
    public function __construct(public string $night, public string $member)
    {
    }
}
