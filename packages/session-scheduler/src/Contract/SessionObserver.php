<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Contract;

use Tilttek\SessionScheduler\Event\ScheduleEvent;

/**
 * Told of each fact once the series or the session is kept, by identifiers only.
 */
interface SessionObserver
{
    public function notify(ScheduleEvent $event): void;
}
