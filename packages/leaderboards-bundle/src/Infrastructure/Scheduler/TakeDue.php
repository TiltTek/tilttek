<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Scheduler;

/**
 * The message the schedule `tilttek_leaderboards` sends: look for the snapshots and the settlements due. It carries
 * nothing; what is due is read from the stores when it is handled.
 */
final readonly class TakeDue
{
}
