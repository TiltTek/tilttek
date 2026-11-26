<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Tests\Unit\Fixture;

use Override;
use Tilttek\SessionScheduler\Contract\DateProposer;
use Tilttek\SessionScheduler\Contract\SessionObserver;
use Tilttek\SessionScheduler\DateProposal;
use Tilttek\SessionScheduler\Event\ScheduleEvent;

/**
 * Records what the scheduler tells and proposes, in order.
 */
final class Observed implements SessionObserver, DateProposer
{
    /** @var list<ScheduleEvent> */
    public array $events = [];

    /** @var list<DateProposal> */
    public array $proposals = [];

    #[Override]
    public function notify(ScheduleEvent $event): void
    {
        $this->events[] = $event;
    }

    #[Override]
    public function propose(DateProposal $proposal): void
    {
        $this->proposals[] = $proposal;
    }

    /**
     * The events told since the last call.
     *
     * @return list<ScheduleEvent>
     */
    public function take(): array
    {
        [$events, $this->events] = [$this->events, []];

        return $events;
    }
}
