<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Scheduler;

use Override;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The schedule `tilttek_leaderboards`: a `TakeDue` message at the frequency the configuration gives — every hour by
 * default —, which a worker consumes from the transport `scheduler_tilttek_leaderboards`. The boards keep their own
 * timezones, so the snapshots and the weeks due are found whatever the hour.
 */
final readonly class LeaderboardsSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private string $frequency = '1 hour',
    ) {
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        return new Schedule()->add(RecurringMessage::every($this->frequency, new TakeDue()));
    }
}
