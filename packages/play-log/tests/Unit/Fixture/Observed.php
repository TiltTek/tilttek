<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Tests\Unit\Fixture;

use Override;
use Tilttek\PlayLog\Contract\PlayObserver;
use Tilttek\PlayLog\Event\PlayEvent;

/**
 * An observer that keeps the events it is told, in order.
 */
final class Observed implements PlayObserver
{
    /** @var list<PlayEvent> */
    private array $events = [];

    #[Override]
    public function notify(PlayEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * The events told since the last call, which are forgotten.
     *
     * @return list<PlayEvent>
     */
    public function take(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}
