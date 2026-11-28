<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Event;

/**
 * Days were spent on an activity, and some remain.
 */
final readonly class ActivityProgressed implements DowntimeEvent
{
    public function __construct(public string $entry, public string $campaign, public string $character)
    {
    }
}
