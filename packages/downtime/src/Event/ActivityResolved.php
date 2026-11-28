<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Event;

/**
 * An activity ended with an outcome, which awaits the game master.
 */
final readonly class ActivityResolved implements DowntimeEvent
{
    public function __construct(public string $entry, public string $campaign, public string $character)
    {
    }
}
