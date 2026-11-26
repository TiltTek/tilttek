<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Tests\Unit\Fixture;

use Override;
use Tilttek\TurnClock\Contract\ClockObserver;
use Tilttek\TurnClock\Event\ClockEvent;

/**
 * Records what the clock tells, in order.
 */
final class Observed implements ClockObserver
{
    /** @var list<ClockEvent> */
    public array $events = [];

    #[Override]
    public function notify(ClockEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * The events told since the last call.
     *
     * @return list<ClockEvent>
     */
    public function take(): array
    {
        [$events, $this->events] = [$this->events, []];

        return $events;
    }
}
