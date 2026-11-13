<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Tests\Unit\Fixture;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The events dispatched, in order.
 */
final class Recorder implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    #[Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }

    /**
     * @return list<class-string>
     */
    public function classes(): array
    {
        return array_map(static fn (object $event): string => $event::class, $this->events);
    }
}
