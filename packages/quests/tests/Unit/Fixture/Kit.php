<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit\Fixture;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Projection\InMemoryCheckpoints;
use Tilttek\Activity\Projection\LiveProjections;
use Tilttek\Activity\Projection\Projections;
use Tilttek\Activity\Projection\Projector;
use Tilttek\Quests\Catalog;
use Tilttek\Quests\Duel\Duels;
use Tilttek\Quests\Evaluator;
use Tilttek\Quests\InMemoryCatalog;
use Tilttek\Quests\QuestProjection;
use Tilttek\Quests\QuestReader;
use Tilttek\Quests\Quests;
use Tilttek\Quests\Store\InMemoryCompletionStore;
use Tilttek\Quests\Store\InMemoryContributionStore;
use Tilttek\Quests\Store\InMemoryDuelStore;
use Tilttek\Quests\Store\InMemoryEnrolmentStore;

/**
 * Everything a test of the quests needs, wired together in memory: the journal keeps the projection up to date as
 * activities are recorded, at the moment the clock says.
 */
final readonly class Kit
{
    public Clock $clock;

    public Recorder $recorder;

    public InMemoryContributionStore $contributions;

    public InMemoryEnrolmentStore $enrolments;

    public InMemoryCompletionStore $completions;

    public InMemoryDuelStore $duels;

    public Evaluator $evaluator;

    public QuestProjection $projection;

    public Quests $quests;

    public Duels $duelService;

    public InMemoryActivityLog $log;

    public Projector $projector;

    public Journal $journal;

    public function __construct(
        public Catalog $catalog,
        string $now = '2026-11-07 12:00:00 UTC',
    ) {
        $this->clock = new Clock($now);
        $this->recorder = new Recorder();
        $this->contributions = new InMemoryContributionStore();
        $this->enrolments = new InMemoryEnrolmentStore();
        $this->completions = new InMemoryCompletionStore();
        $this->duels = new InMemoryDuelStore();
        $this->evaluator = new Evaluator($catalog, $this->contributions, $this->enrolments, $this->completions, $this->duels);
        $this->projection = new QuestProjection($this->evaluator, $this->recorder);
        $this->quests = new Quests($this->evaluator, $this->clock);
        $this->duelService = new Duels($this->evaluator, $this->clock, $this->recorder);
        $this->log = new InMemoryActivityLog();
        $this->projector = new Projector($this->log, new InMemoryCheckpoints());
        $this->journal = new Journal($this->log, $this->clock, observers: [new LiveProjections($this->projector, new Projections([$this->projection]))]);
    }

    /**
     * A kit over the quests of a configuration, read in UTC.
     *
     * @param array<string, array<string, mixed>> $definitions
     */
    public static function of(array $definitions, string $now = '2026-11-07 12:00:00 UTC'): self
    {
        return new self(InMemoryCatalog::fromArray($definitions, new QuestReader('UTC')), $now);
    }

    /**
     * Records an activity at the moment it occurred, the clock set to it first.
     *
     * @param array<string, bool|int|string> $context
     */
    public function record(string $key, string $member, string $verb, string $at, int $quantity = 1, array $context = []): void
    {
        $this->clock->set($at.' UTC');
        $this->journal->record(Activity::of($key, $member, $verb, new DateTimeImmutable($at.' UTC'), null, $quantity, $context));
    }

    public function cancel(string $key, string $at): void
    {
        $this->clock->set($at.' UTC');
        $this->journal->cancel($key);
    }

    public function at(string $now): void
    {
        $this->clock->set($now.' UTC');
    }

    public static function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }
}
