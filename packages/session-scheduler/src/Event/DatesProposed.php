<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Event;

/**
 * Dates were put to the members of a series, for a new session or, when `session` is given, to replace one.
 */
final readonly class DatesProposed implements ScheduleEvent
{
    public function __construct(public string $series, public ?string $session)
    {
    }
}
