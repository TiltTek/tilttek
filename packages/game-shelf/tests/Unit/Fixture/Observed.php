<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Tests\Unit\Fixture;

use Override;
use Tilttek\GameShelf\Contract\ShelfObserver;
use Tilttek\GameShelf\Event\ShelfEvent;

/**
 * An observer that keeps the events it is told, in order.
 */
final class Observed implements ShelfObserver
{
    /** @var list<ShelfEvent> */
    private array $events = [];

    #[Override]
    public function notify(ShelfEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * The events told since the last call, which are forgotten.
     *
     * @return list<ShelfEvent>
     */
    public function take(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}
