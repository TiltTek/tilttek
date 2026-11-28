<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Event;

/**
 * A character chose an activity: the application debits its costs.
 */
final readonly class ActivityChosen implements DowntimeEvent
{
    public function __construct(public string $entry, public string $campaign, public string $character)
    {
    }
}
