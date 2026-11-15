<?php

declare(strict_types=1);

namespace Tilttek\Sets\Symfony;

use DateInterval;
use Override;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Tilttek\Sets\Exception\InvalidSet;

/**
 * The schedule `tilttek_sets`: a `DeliverPendingCompletions` message at every interval, five minutes by default, so
 * that the completions whose handler failed are told again without waiting for their holders' holdings to change.
 *
 * A run missed while no worker consumed the schedule is run once, not once per interval. With a lock, two workers that
 * consume the schedule do not both send the message; without one, both may, and the completions are told by each — the
 * handlers of the events are idempotent.
 */
#[AsSchedule(self::NAME)]
final readonly class DeliverySchedule implements ScheduleProviderInterface
{
    public const string NAME = 'tilttek_sets';

    private DeliverPendingCompletions $message;

    /**
     * @param string|int|DateInterval $frequency the interval between two runs: a relative format such as `5 minutes`,
     *                                           seconds, or an interval
     * @param int                     $limit     the most holders a run tells
     * @param ?LockInterface          $lock      a lock shared by the workers that consume the schedule
     *
     * @throws InvalidSet when the limit is out of bounds
     */
    public function __construct(
        private string|int|DateInterval $frequency = '5 minutes',
        int $limit = DeliverPendingCompletions::LIMIT,
        private ?LockInterface $lock = null,
    ) {
        $this->message = new DeliverPendingCompletions($limit);
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        $schedule = new Schedule()
            ->add(RecurringMessage::every($this->frequency, $this->message))
            ->processOnlyLastMissedRun(true);

        return $this->lock instanceof LockInterface ? $schedule->lock($this->lock) : $schedule;
    }
}
