<?php

declare(strict_types=1);

namespace Tilttek\Activity\Tests\Unit\Projection;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Exception\InvalidActivity;
use Tilttek\Activity\Exception\InvalidProjection;
use Tilttek\Activity\Exception\UnknownProjection;
use Tilttek\Activity\Filter;
use Tilttek\Activity\IdempotencyKey;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Projection\InMemoryCheckpoints;
use Tilttek\Activity\Projection\LiveProjections;
use Tilttek\Activity\Projection\Projections;
use Tilttek\Activity\Projection\Projector;
use Tilttek\Activity\Tests\Unit\Fixture\Clock;
use Tilttek\Activity\Tests\Unit\Fixture\Counter;

#[CoversClass(Projector::class)]
#[CoversClass(Projections::class)]
#[CoversClass(LiveProjections::class)]
#[CoversClass(InMemoryCheckpoints::class)]
#[CoversClass(InvalidProjection::class)]
#[CoversClass(UnknownProjection::class)]
final class ProjectorTest extends TestCase
{
    private InMemoryActivityLog $log;

    private InMemoryCheckpoints $checkpoints;

    protected function setUp(): void
    {
        $this->log = new InMemoryActivityLog();
        $this->checkpoints = new InMemoryCheckpoints();
    }

    public function testAProjectionCatchesUpInBatches(): void
    {
        foreach (range(1, 5) as $i) {
            $this->append('k'.$i, 'member:'.($i % 2), 'game.played', $i);
        }

        $counter = new Counter();
        $projector = new Projector($this->log, $this->checkpoints, 2);
        self::assertSame(0, $this->checkpoints->position('counter'));
        self::assertSame(5, $projector->lag($counter));

        self::assertSame(5, $projector->catchUp($counter));
        self::assertSame([1, 2, 3, 4, 5], $counter->applied);
        self::assertSame(['member:1' => 9, 'member:0' => 6], $counter->totals);
        self::assertSame(5, $this->checkpoints->position('counter'));
        self::assertSame(0, $projector->lag($counter));

        self::assertSame(0, $projector->catchUp($counter));
        $this->append('k6', 'member:0', 'game.played', 6);
        $this->log->cancel(IdempotencyKey::fromString('k1'), new DateTimeImmutable());
        self::assertSame(2, $projector->lag($counter));
        self::assertSame(2, $projector->catchUp($counter));
        self::assertSame([1, 2, 3, 4, 5, 6, 7], $counter->applied);
        self::assertSame(['member:1' => 8, 'member:0' => 12], $counter->totals);
    }

    public function testABatchThatEndsTheJournalExactlyIsFollowedByAnEmptyOne(): void
    {
        foreach (range(1, 4) as $i) {
            $this->append('k'.$i, 'member:1', 'game.played');
        }

        $counter = new Counter();
        self::assertSame(4, new Projector($this->log, $this->checkpoints, 2)->catchUp($counter));
        self::assertSame(4, $this->checkpoints->position('counter'));
        self::assertSame(4, new Projector($this->log, $this->checkpoints, 4)->catchUp(new Counter('other')));
    }

    public function testEntriesTheFilterLeavesOutMoveTheCheckpoint(): void
    {
        $this->append('a', 'member:1', 'game.played');
        $this->append('b', 'member:1', 'news.read');
        $this->append('c', 'member:1', 'news.read');

        $counter = new Counter('games', Filter::all()->verbs('game.*'));
        $projector = new Projector($this->log, $this->checkpoints, 1);
        self::assertSame(1, $projector->catchUp($counter));
        self::assertSame([1], $counter->applied);
        self::assertSame(3, $this->checkpoints->position('games'));

        $this->append('d', 'member:1', 'news.read');
        $this->append('e', 'member:1', 'game.won');
        self::assertSame(1, $projector->catchUp($counter));
        self::assertSame([1, 5], $counter->applied);
        self::assertSame(5, $this->checkpoints->position('games'));
    }

    public function testAFailureKeepsTheCheckpointBeforeTheFailedBatch(): void
    {
        foreach (range(1, 5) as $i) {
            $this->append('k'.$i, 'member:1', 'game.played');
        }

        $counter = new Counter();
        $counter->failOn = 4;

        $projector = new Projector($this->log, $this->checkpoints, 2);

        try {
            $projector->catchUp($counter);
            self::fail('The failure was swallowed.');
        } catch (RuntimeException) {
        }

        self::assertSame(2, $this->checkpoints->position('counter'));
        $counter->failOn = null;
        self::assertSame(3, $projector->catchUp($counter));
        self::assertSame([1, 2, 3, 3, 4, 5], $counter->applied);
    }

    public function testARebuildReplaysTheJournalOrAPeriodOfIt(): void
    {
        $this->append('a', 'member:1', 'game.played', 1, '2026-10-01');
        $this->append('b', 'member:1', 'game.played', 2, '2026-11-01');
        $this->append('c', 'member:1', 'game.played', 4, '2026-11-02');

        $counter = new Counter();
        $projector = new Projector($this->log, $this->checkpoints);
        $projector->catchUp($counter);

        $counter->applied = [];

        self::assertSame(3, $projector->rebuild($counter));
        self::assertSame([null], $counter->resets);
        self::assertSame([1, 2, 3], $counter->applied);
        self::assertSame(['member:1' => 7], $counter->totals);
        self::assertSame(3, $this->checkpoints->position('counter'));

        $counter->applied = [];
        self::assertSame(2, $projector->rebuild($counter, new DateTimeImmutable('2026-11-01 00:00:00 UTC')));
        self::assertSame([null, '2026-11-01'], $counter->resets);
        self::assertSame([2, 3], $counter->applied);
        self::assertSame(3, $this->checkpoints->position('counter'));
    }

    public function testARebuildKeepsTheLaterOfTwoStarts(): void
    {
        $this->append('a', 'member:1', 'game.played', 1, '2026-10-01');
        $this->append('b', 'member:1', 'game.played', 2, '2026-11-01');
        $this->append('c', 'member:1', 'game.played', 4, '2026-11-02');
        $projector = new Projector($this->log, $this->checkpoints);

        $late = new Counter('late', Filter::all()->since(new DateTimeImmutable('2026-11-02 00:00:00 UTC')));
        self::assertSame(1, $projector->rebuild($late, new DateTimeImmutable('2026-10-15 00:00:00 UTC')));
        self::assertSame([3], $late->applied);

        $early = new Counter('early', Filter::all()->since(new DateTimeImmutable('2026-10-15 00:00:00 UTC')));
        self::assertSame(1, $projector->rebuild($early, new DateTimeImmutable('2026-11-02 00:00:00 UTC')));
        self::assertSame([3], $early->applied);

        $same = new Counter('same', Filter::all()->since(new DateTimeImmutable('2026-11-01 00:00:00 UTC')));
        self::assertSame(2, $projector->rebuild($same, new DateTimeImmutable('2026-11-01 00:00:00 UTC')));
    }

    public function testTheDefaultBatchHoldsFiveHundredEntries(): void
    {
        foreach (range(1, 501) as $i) {
            $this->append('k'.$i, 'member:1', 'game.played');
        }

        $counter = new Counter();
        $counter->failOn = 501;

        try {
            new Projector($this->log, $this->checkpoints)->catchUp($counter);
            self::fail('The failure was swallowed.');
        } catch (RuntimeException) {
        }

        self::assertSame(500, $this->checkpoints->position('counter'));
    }

    public function testTheBatchSizeIsAtLeastOne(): void
    {
        self::assertSame(0, new Projector($this->log, $this->checkpoints, 1)->catchUp(new Counter()));

        $this->expectException(InvalidActivity::class);
        $this->expectExceptionMessageIsOrContains('A batch holds at least one entry; 0 is not a batch size.');

        new Projector($this->log, $this->checkpoints, 0);
    }

    public function testTheLagIsNeverNegative(): void
    {
        $this->checkpoints->save('counter', 10);

        self::assertSame(0, new Projector($this->log, $this->checkpoints)->lag(new Counter()));
    }

    public function testProjectionsAreFoundByNameAndSorted(): void
    {
        $points = new Counter('points');
        $badges = new Counter('badges.progress');
        $projections = new Projections(['x' => $points, 'y' => $badges]);

        self::assertSame([$badges, $points], $projections->all());
        self::assertSame($points, $projections->get('points'));
        self::assertSame([], new Projections()->all());
        self::assertCount(2, new Projections([new Counter('a'), new Counter(str_repeat('a', 64))])->all());

        $this->expectException(UnknownProjection::class);
        $this->expectExceptionMessageIsOrContains('No projection is named "quests".');

        $projections->get('quests');
    }

    public function testTwoProjectionsCannotShareAName(): void
    {
        $this->expectException(InvalidProjection::class);
        $this->expectExceptionMessageIsOrContains('Two projections are named "points".');

        new Projections([new Counter('points'), new Counter('points')]);
    }

    public function testAProjectionNameIsChecked(): void
    {
        foreach (['Points', '1points', 'points!', '', str_repeat('a', 65), "points\n"] as $name) {
            try {
                new Projections([new Counter($name)]);
                self::fail('The name "'.$name.'" was accepted.');
            } catch (InvalidProjection $exception) {
                self::assertSame('"'.$name.'" is not a projection name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.', $exception->getMessage());
            }
        }
    }

    public function testLiveProjectionsFollowTheJournal(): void
    {
        $games = new Counter('games', Filter::all()->verbs('game.*'));
        $all = new Counter('all');
        $projector = new Projector($this->log, $this->checkpoints);
        $journal = new Journal($this->log, new Clock(), observers: [new LiveProjections($projector, new Projections([$games, $all]))]);

        $journal->record(Activity::of('a', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07 10:00:00 UTC')));
        $journal->record(Activity::of('b', 'member:1', 'news.read', new DateTimeImmutable('2026-11-07 10:00:00 UTC')));
        self::assertSame([1], $games->applied);
        self::assertSame([1, 2], $all->applied);
        self::assertSame(1, $this->checkpoints->position('games'));
        self::assertSame(2, $this->checkpoints->position('all'));

        $journal->cancel('a');
        self::assertSame([1, 3], $games->applied);
        self::assertSame([1, 2, 3], $all->applied);
    }

    private function append(string $key, string $member, string $verb, int $quantity = 1, string $at = '2026-11-07'): void
    {
        $this->log->append(Activity::of($key, $member, $verb, new DateTimeImmutable($at.' 00:00:00 UTC'), quantity: $quantity), new DateTimeImmutable());
    }
}
