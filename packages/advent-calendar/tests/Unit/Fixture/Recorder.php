<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar\Tests\Unit\Fixture;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * A dispatcher that writes down the events it is given.
 */
final class Recorder implements EventDispatcherInterface
{
    /**
     * @var list<object>
     */
    public array $events = [];

    #[Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }
}
