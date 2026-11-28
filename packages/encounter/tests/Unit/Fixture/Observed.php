<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Tests\Unit\Fixture;

use Override;
use Tilttek\Encounter\Contract\EncounterObserver;
use Tilttek\Encounter\Event\EncounterEvent;

/**
 * Records what the service tells, in order.
 */
final class Observed implements EncounterObserver
{
    /** @var list<EncounterEvent> */
    public array $events = [];

    #[Override]
    public function notify(EncounterEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * The events told since the last call.
     *
     * @return list<EncounterEvent>
     */
    public function take(): array
    {
        [$events, $this->events] = [$this->events, []];

        return $events;
    }
}
