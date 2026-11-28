<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Event;

/**
 * An activity was cancelled: the application refunds what its rules refund.
 */
final readonly class ActivityCancelled implements DowntimeEvent
{
    public function __construct(public string $entry, public string $campaign, public string $character)
    {
    }
}
