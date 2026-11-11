<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit\Fixture;

use DateTimeImmutable;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Projection\InMemoryCheckpoints;
use Tilttek\Activity\Projection\LiveProjections;
use Tilttek\Activity\Projection\Projections;
use Tilttek\Activity\Projection\Projector;
use Tilttek\Progression\Progression;
use Tilttek\Progression\ProgressionProjection;
use Tilttek\Progression\Store\InMemoryExperienceStore;
use Tilttek\Progression\Store\InMemoryMilestoneStore;
use Tilttek\Progression\Store\InMemoryStreakStore;
use Tilttek\Progression\Streak\StreakKeeper;
use Tilttek\Progression\Streak\StreakRules;
use Tilttek\Progression\Streak\TimezoneResolver;
use Tilttek\Progression\Tracks;

/**
 * Everything a test of the progression needs, wired together in memory: the journal keeps the projection up to date
 * as activities are recorded, at the moment the clock says.
 */
final readonly class Kit
{
    public Clock $clock;

    public Recorder $recorder;

    public InMemoryExperienceStore $experience;

    public InMemoryMilestoneStore $milestones;

    public InMemoryStreakStore $streakStore;

    public StreakKeeper $keeper;

    public ProgressionProjection $projection;

    public Progression $progression;

    public InMemoryActivityLog $log;

    public Projector $projector;

    public Journal $journal;

    public function __construct(
        public Tracks $tracks,
        public StreakRules $streaks,
        ?TimezoneResolver $timezones = null,
        string $now = '2026-11-07 12:00:00 UTC',
    ) {
        $this->clock = new Clock($now);
        $this->recorder = new Recorder();
        $this->experience = new InMemoryExperienceStore();
        $this->milestones = new InMemoryMilestoneStore();
        $this->streakStore = new InMemoryStreakStore();
        $this->keeper = new StreakKeeper($streaks, $this->streakStore, $this->recorder);
        $this->projection = new ProgressionProjection($tracks, $this->keeper, $this->experience, $this->milestones, $this->clock, $timezones, $this->recorder);
        $this->progression = new Progression($tracks, $this->keeper, $this->experience, $this->milestones, $this->streakStore, $this->clock);
        $this->log = new InMemoryActivityLog();
        $this->projector = new Projector($this->log, new InMemoryCheckpoints());
        $this->journal = new Journal($this->log, $this->clock, observers: [new LiveProjections($this->projector, new Projections([$this->projection]))]);
    }

    /**
     * Records an activity at the moment it occurred, the clock set to it first.
     *
     * @param array<string, bool|int|string> $context
     */
    public function record(string $key, string $member, string $verb, string $at, ?string $object = null, int $quantity = 1, array $context = []): void
    {
        $this->clock->set($at.' UTC');
        $this->journal->record(Activity::of($key, $member, $verb, new DateTimeImmutable($at.' UTC'), $object, $quantity, $context));
    }
}
