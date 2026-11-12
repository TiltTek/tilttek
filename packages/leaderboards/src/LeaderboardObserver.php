<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

/**
 * What the library tells the application: a snapshot was taken, once per board and period, so that it rewards the
 * first members.
 */
interface LeaderboardObserver
{
    public function snapshotTaken(Snapshot $snapshot): void;
}
