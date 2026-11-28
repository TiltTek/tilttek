<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Event;

/**
 * The last free seat of the night was taken: the sign-ups that follow wait on the waiting list.
 */
final readonly class NightFull implements NightEvent
{
    public function __construct(public string $night)
    {
    }
}
