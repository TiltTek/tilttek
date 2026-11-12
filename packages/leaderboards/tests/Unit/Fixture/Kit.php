<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\Fixture;

use DateTimeImmutable;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Projection\InMemoryCheckpoints;
use Tilttek\Activity\Projection\LiveProjections;
use Tilttek\Activity\Projection\Projections;
use Tilttek\Activity\Projection\Projector;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\LeaderboardProjection;
use Tilttek\Leaderboards\Leaderboards;
use Tilttek\Leaderboards\League\Leagues;
use Tilttek\Leaderboards\Store\InMemoryAppearanceStore;
use Tilttek\Leaderboards\Store\InMemoryLeagueStore;
use Tilttek\Leaderboards\Store\InMemoryScoreStore;
use Tilttek\Leaderboards\Store\InMemorySnapshotStore;

/**
 * Everything a test of the leaderboards needs, wired together in memory, leagues included: the journal keeps the projection up to date
 * as activities are recorded, at the moment the clock says.
 */
final readonly class Kit
{
    public Clock $clock;

    public Recorder $recorder;

    public InMemoryScoreStore $scores;

    public InMemorySnapshotStore $snapshots;

    public InMemoryAppearanceStore $appearances;

    public InMemoryLeagueStore $leagueStore;

    public Leagues $leagues;

    public LeaderboardProjection $projection;

    public Leaderboards $leaderboards;

    public InMemoryActivityLog $log;

    public Projector $projector;

    public Journal $journal;

    public function __construct(
        public Boards $boards,
        string $now = '2026-11-07 12:00:00 UTC',
        string $secret = 'a secret of the tests',
    ) {
        $this->clock = new Clock($now);
        $this->recorder = new Recorder();
        $this->scores = new InMemoryScoreStore();
        $this->snapshots = new InMemorySnapshotStore();
        $this->appearances = new InMemoryAppearanceStore();
        $this->leagueStore = new InMemoryLeagueStore();
        $this->leagues = new Leagues($boards, $this->scores, $this->leagueStore, $this->clock, $this->recorder);
        $this->projection = new LeaderboardProjection($boards, $this->scores, leagues: $this->leagues, clock: $this->clock);
        $this->leaderboards = new Leaderboards($boards, $this->scores, $this->snapshots, $this->appearances, $this->clock, $this->recorder, $this->leagues, $secret);
        $this->log = new InMemoryActivityLog();
        $this->projector = new Projector($this->log, new InMemoryCheckpoints());
        $this->journal = new Journal($this->log, $this->clock, observers: [new LiveProjections($this->projector, new Projections([$this->projection]))], past: null);
    }

    /**
     * Records an activity at the moment it occurred, in UTC.
     *
     * @param array<string, bool|int|string> $context
     */
    public function record(string $key, string $member, string $verb, string $at, int $quantity = 1, array $context = []): void
    {
        $this->journal->record(Activity::of($key, $member, $verb, new DateTimeImmutable($at.' UTC'), null, $quantity, $context));
    }

    /**
     * Records an activity on an object at the moment it occurred, in UTC.
     *
     * @param array<string, bool|int|string> $context
     */
    public function recordOn(string $key, string $member, string $verb, string $at, string $object, int $quantity = 1, array $context = []): void
    {
        $this->journal->record(Activity::of($key, $member, $verb, new DateTimeImmutable($at.' UTC'), $object, $quantity, $context));
    }
}
