<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Tests\Unit\Fixture;

use Override;
use Tilttek\GameNight\Contract\NightObserver;
use Tilttek\GameNight\Event\NightEvent;

/**
 * An observer that keeps the events it is told, in order.
 */
final class Observed implements NightObserver
{
    /** @var list<NightEvent> */
    private array $events = [];

    #[Override]
    public function notify(NightEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * The events told since the last call, which are forgotten.
     *
     * @return list<NightEvent>
     */
    public function take(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}
