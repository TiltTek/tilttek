<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Event;

/**
 * The night opened to sign-ups: the moment to announce it.
 */
final readonly class NightPublished implements NightEvent
{
    public function __construct(public string $night)
    {
    }
}
