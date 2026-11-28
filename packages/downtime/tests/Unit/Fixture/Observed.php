<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Tests\Unit\Fixture;

use Override;
use Tilttek\Downtime\Contract\DowntimeObserver;
use Tilttek\Downtime\Event\DowntimeEvent;

/**
 * Records what the service tells, in order.
 */
final class Observed implements DowntimeObserver
{
    /** @var list<DowntimeEvent> */
    public array $events = [];

    #[Override]
    public function notify(DowntimeEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * The events told since the last call.
     *
     * @return list<DowntimeEvent>
     */
    public function take(): array
    {
        [$events, $this->events] = [$this->events, []];

        return $events;
    }
}
