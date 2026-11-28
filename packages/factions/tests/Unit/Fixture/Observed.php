<?php

declare(strict_types=1);

namespace Tilttek\Factions\Tests\Unit\Fixture;

use Override;
use Tilttek\Factions\Contract\RosterObserver;
use Tilttek\Factions\Event\RosterEvent;

/**
 * Records what the service tells, in order.
 */
final class Observed implements RosterObserver
{
    /** @var list<RosterEvent> */
    public array $events = [];

    #[Override]
    public function notify(RosterEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * The events told since the last call.
     *
     * @return list<RosterEvent>
     */
    public function take(): array
    {
        [$events, $this->events] = [$this->events, []];

        return $events;
    }
}
