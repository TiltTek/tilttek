<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Scheduler;

use Override;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The schedule `tilttek_escape_room`: an `ExpireDue` message at the frequency the configuration gives — every
 * 15 seconds by default —, which a worker consumes from the transport `scheduler_tilttek_escape_room`.
 */
final readonly class EscapeRoomSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private string $frequency = '15 seconds',
    ) {
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        return new Schedule()->add(RecurringMessage::every($this->frequency, new ExpireDue()));
    }
}
