<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\Fixture;

use Override;
use Tilttek\Leaderboards\LeaderboardObserver;
use Tilttek\Leaderboards\League\LeagueObserver;
use Tilttek\Leaderboards\League\Membership;
use Tilttek\Leaderboards\League\Settlement;
use Tilttek\Leaderboards\Placing;
use Tilttek\Leaderboards\Snapshot;

/**
 * An observer that writes down what it is told, one line each.
 */
final class Recorder implements LeaderboardObserver, LeagueObserver
{
    /**
     * @var list<string>
     */
    public array $told = [];

    #[Override]
    public function snapshotTaken(Snapshot $snapshot): void
    {
        $this->told[] = \sprintf('snapshot %s %s %d [%s]', $snapshot->board, $snapshot->period, $snapshot->participants, implode(' ', array_map(static fn (Placing $placing): string => $placing->rank.':'.$placing->member->toString().'='.$placing->value, $snapshot->placings)));
    }

    #[Override]
    public function leagueSettled(Settlement $settlement): void
    {
        $this->told[] = \sprintf('settled %s %s [%s]', $settlement->board, $settlement->week, implode(' ', array_map(static fn (Membership $membership): string => $membership->member->toString().'>'.$membership->movement?->value, $settlement->memberships)));
    }
}
