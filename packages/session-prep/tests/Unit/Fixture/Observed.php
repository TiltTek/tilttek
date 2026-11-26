<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Tests\Unit\Fixture;

use Override;
use Tilttek\SessionPrep\Contract\PreparationObserver;
use Tilttek\SessionPrep\Event\PreparationEvent;

/**
 * Records what the preparer tells, in order.
 */
final class Observed implements PreparationObserver
{
    /** @var list<PreparationEvent> */
    public array $events = [];

    #[Override]
    public function notify(PreparationEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * The events told since the last call.
     *
     * @return list<PreparationEvent>
     */
    public function take(): array
    {
        [$events, $this->events] = [$this->events, []];

        return $events;
    }
}
