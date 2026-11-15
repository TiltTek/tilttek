<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Functional\App;

use Tilttek\Leaderboards\League\Membership;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\LeagueSettled;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\SnapshotTaken;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\TopReached;

/**
 * A listener that writes down the snapshots, the settlements and the tops reached the bundle announces, one line each.
 */
final class Recorder
{
    /**
     * @var list<string>
     */
    public array $events = [];

    public function snapshot(SnapshotTaken $event): void
    {
        $this->events[] = \sprintf('snapshot %s %s %d', $event->snapshot->board, $event->snapshot->period, $event->snapshot->participants);
    }

    public function settled(LeagueSettled $event): void
    {
        $this->events[] = \sprintf('settled %s %s [%s]', $event->settlement->board, $event->settlement->week, implode(' ', array_map(static fn (Membership $membership): string => $membership->member->toString().'>'.$membership->movement?->value, $event->settlement->memberships)));
    }

    public function top(TopReached $event): void
    {
        $this->events[] = \sprintf('top %s %s %s %s %s', $event->board, $event->period, $event->member->toString(), $event->summit->value, $event->appearance->visibility->value);
    }
}
