<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The events dispatched, in order, each first given to the listener of the test.
 */
final class Recorder implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    /**
     * @param (Closure(object): void)|null $listener
     */
    public function __construct(
        public ?Closure $listener = null,
    ) {
    }

    #[Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        if ($this->listener instanceof Closure) {
            ($this->listener)($event);
        }

        return $event;
    }
}
