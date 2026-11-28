<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Tests\Unit\Fixture;

use Override;
use Tilttek\TableRules\Contract\TableRulesObserver;
use Tilttek\TableRules\Event\RulesEvent;

/**
 * Records what the service tells, in order.
 */
final class Observed implements TableRulesObserver
{
    /** @var list<RulesEvent> */
    public array $events = [];

    #[Override]
    public function notify(RulesEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * The events told since the last call.
     *
     * @return list<RulesEvent>
     */
    public function take(): array
    {
        [$events, $this->events] = [$this->events, []];

        return $events;
    }
}
