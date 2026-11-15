<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Scheduler;

use Psr\Clock\ClockInterface;
use Tilttek\Leaderboards\LeaderboardProjection;
use Tilttek\Leaderboards\Leaderboards;
use Tilttek\Leaderboards\League\Leagues;

/**
 * Handles `TakeDue`, which it is registered for: takes the snapshots due, settles the weeks of the leagues due — each
 * once whoever asks first: the schedule, the commands, or a member entering the next week —, and takes out of the
 * sliding windows the gestures grown too old. The message carries nothing the handler reads.
 */
final readonly class TakeDueHandler
{
    public function __construct(
        private Leaderboards $leaderboards,
        private Leagues $leagues,
        private LeaderboardProjection $projection,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{snapshots: int, settlements: int, expired: int} how many were taken, settled and taken out of a window
     */
    public function __invoke(): array
    {
        return ['snapshots' => \count($this->leaderboards->snapshotDue()), 'settlements' => \count($this->leagues->settleDue()), 'expired' => $this->projection->slide($this->clock->now())];
    }
}
