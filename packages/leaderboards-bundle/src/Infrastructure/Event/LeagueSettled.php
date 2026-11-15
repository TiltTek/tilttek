<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Event;

use Tilttek\Leaderboards\League\Settlement;

/**
 * A week of a league was settled, once: a listener congratulates the members who went up — a notification, a badge —
 * through the packages the application has.
 */
final readonly class LeagueSettled
{
    public function __construct(
        public Settlement $settlement,
    ) {
    }
}
