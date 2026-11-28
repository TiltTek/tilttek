<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Tests\Unit\Fixture;

use Override;
use Tilttek\InitiativeCards\Contract\InitiativeObserver;
use Tilttek\InitiativeCards\Event\InitiativeEvent;

/**
 * Records what the tracker tells, in order.
 */
final class Observed implements InitiativeObserver
{
    /** @var list<InitiativeEvent> */
    public array $events = [];

    #[Override]
    public function notify(InitiativeEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * The events told since the last call.
     *
     * @return list<InitiativeEvent>
     */
    public function take(): array
    {
        [$events, $this->events] = [$this->events, []];

        return $events;
    }
}
