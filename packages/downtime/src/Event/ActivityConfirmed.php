<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Event;

/**
 * The game master confirmed the outcome of an activity: the application applies its effects.
 */
final readonly class ActivityConfirmed implements DowntimeEvent
{
    public function __construct(public string $entry, public string $campaign, public string $character)
    {
    }
}
