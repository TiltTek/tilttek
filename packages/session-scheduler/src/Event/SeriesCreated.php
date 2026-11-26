<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Event;

/**
 * A series was created.
 */
final readonly class SeriesCreated implements ScheduleEvent
{
    public function __construct(public string $series)
    {
    }
}
