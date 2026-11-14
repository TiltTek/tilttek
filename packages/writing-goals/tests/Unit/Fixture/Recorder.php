<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Tests\Unit\Fixture;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * A dispatcher that writes down the events it is given; its listener fails as many times as it is told.
 */
final class Recorder implements EventDispatcherInterface
{
    /**
     * @var list<object>
     */
    public array $events = [];

    public int $failures = 0;

    #[Override]
    public function dispatch(object $event): object
    {
        if ($this->failures > 0) {
            --$this->failures;

            throw new RuntimeException('The listener failed.');
        }

        $this->events[] = $event;

        return $event;
    }
}
