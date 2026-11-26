<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Event;

/**
 * The game master changed a series: its rule, its players or its settings. The sessions already planned keep their dates.
 */
final readonly class SeriesChanged implements ScheduleEvent
{
    public function __construct(public string $series)
    {
    }
}
