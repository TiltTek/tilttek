<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Event;

/**
 * All the days of an activity were spent, and its roll is awaited.
 */
final readonly class ActivityCompleted implements DowntimeEvent
{
    public function __construct(public string $entry, public string $campaign, public string $character)
    {
    }
}
