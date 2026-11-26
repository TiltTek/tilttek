<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Tests\Unit\Fixture;

use Override;
use Tilttek\Conditions\Contract\ConditionObserver;
use Tilttek\Conditions\Event\ConditionEvent;

/**
 * Records what the service tells, in order.
 */
final class Observed implements ConditionObserver
{
    /** @var list<ConditionEvent> */
    public array $events = [];

    #[Override]
    public function notify(ConditionEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * The events told since the last call.
     *
     * @return list<ConditionEvent>
     */
    public function take(): array
    {
        [$events, $this->events] = [$this->events, []];

        return $events;
    }
}
