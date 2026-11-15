<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Event;

use Tilttek\Leaderboards\Snapshot;

/**
 * A board was frozen at the end of a period, once: a listener rewards its first members — points, a badge, a title —
 * through the packages the application has.
 */
final readonly class SnapshotTaken
{
    public function __construct(
        public Snapshot $snapshot,
    ) {
    }
}
