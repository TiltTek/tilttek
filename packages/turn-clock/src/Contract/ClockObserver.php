<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Contract;

use Tilttek\TurnClock\Event\ClockEvent;

/**
 * Told of each fact once the clock is kept, by identifiers only.
 */
interface ClockObserver
{
    public function notify(ClockEvent $event): void;
}
