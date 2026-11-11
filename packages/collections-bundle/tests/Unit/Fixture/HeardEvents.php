<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Unit\Fixture;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * An event dispatcher that keeps what it is given.
 */
final class HeardEvents implements EventDispatcherInterface
{
    /**
     * @var list<object>
     */
    private array $events = [];

    #[Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }

    /**
     * @return list<object>
     */
    public function events(): array
    {
        return $this->events;
    }
}
