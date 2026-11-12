<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Tests\Unit;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\WritingGoals\Day;
use Tilttek\WritingGoals\Event\GoalReached;
use Tilttek\WritingGoals\Exception\InvalidGoal;
use Tilttek\WritingGoals\Exception\UnknownGoal;
use Tilttek\WritingGoals\Goal;
use Tilttek\WritingGoals\Store\InMemoryGoalStore;
use Tilttek\WritingGoals\Store\InMemoryLogStore;
use Tilttek\WritingGoals\Tests\Unit\Fixture\Recorder;
use Tilttek\WritingGoals\WritingGoals;

#[CoversClass(WritingGoals::class)]
#[CoversClass(GoalReached::class)]
#[CoversClass(UnknownGoal::class)]
final class WritingGoalsTest extends TestCase
{
    private Recorder $events;

    private InMemoryLogStore $logs;

    private WritingGoals $goals;

    private ResourceReference $author;

    protected function setUp(): void
    {
        $clock = new MockClock('2026-11-03 03:30:00', 'UTC');
        $this->events = new Recorder();
        $this->logs = new InMemoryLogStore();
        $this->goals = new WritingGoals(new InMemoryGoalStore(), $this->logs, $clock, $this->events);
        $this->author = ResourceReference::of('member', '1');
        $this->goals->save(new Goal('novel', $this->author, 1000, Day::fromString('2026-11-01'), Day::fromString('2026-11-10'), timezone: 'America/Montreal'));
    }

    public function testTodayIsTheAuthorsToday(): void
    {
        self::assertSame('2026-11-02', $this->goals->today($this->goals->goal('novel'))->toString());
        self::assertSame('2026-11-02', $this->goals->plan('novel')->today->toString());
    }

    public function testLoggingWordsToday(): void
    {
        $this->goals->log('novel', 150);
        $plan = $this->goals->log('novel', 50);

        self::assertSame(['2026-11-02' => 200], $this->logs->progress('novel')->toArray());
        self::assertSame([200, 200, 111, 0], [$plan->written, $plan->writtenToday, $plan->todayQuota, $plan->leftToday()]);
        self::assertSame([], $this->events->events);
    }

    public function testLoggingAnEarlierDayReshapesToday(): void
    {
        $plan = $this->goals->log('novel', 280, Day::fromString('2026-11-01'));

        self::assertSame([280, 0, 80], [$plan->written, $plan->writtenToday, $plan->todayQuota]);
    }

    public function testADayThatHasNotComeIsRefused(): void
    {
        $this->expectException(InvalidGoal::class);
        $this->expectExceptionMessageIsOrContains('Words are logged on a day that has come; 2026-11-03 is after 2026-11-02.');

        $this->goals->log('novel', 100, Day::fromString('2026-11-03'));
    }

    public function testADayBeforeTheGoalsFirstDayIsRefused(): void
    {
        self::assertSame(10, $this->goals->log('novel', 10, Day::fromString('2026-11-01'))->written);

        $this->expectException(InvalidGoal::class);
        $this->expectExceptionMessageIsOrContains("Words are logged from the goal's first day; 2026-10-31 is before 2026-11-01.");

        $this->goals->correct('novel', 100, Day::fromString('2026-10-31'));
    }

    public function testACountTakenBackNeverGoesBelowZero(): void
    {
        $this->goals->log('novel', 100);

        self::assertSame(40, $this->goals->log('novel', -60)->written);
        self::assertSame(0, $this->goals->log('novel', -1000)->written);
    }

    public function testACountLoggedIsBounded(): void
    {
        self::assertSame(1_000_000_000, $this->goals->log('novel', 1_000_000_000)->written);
        self::assertSame(0, $this->goals->log('novel', -1_000_000_000)->written);

        $this->expectException(InvalidGoal::class);
        $this->expectExceptionMessageIsOrContains('A count logged is -1000000000 to 1000000000; -1000000001 is not one.');
        $this->goals->log('novel', -1_000_000_001);
    }

    public function testACountTooHighIsRefused(): void
    {
        $this->expectException(InvalidGoal::class);
        $this->expectExceptionMessageIsOrContains('1000000001 is not one.');
        $this->goals->log('novel', 1_000_000_001);
    }

    public function testCorrectingADay(): void
    {
        $this->goals->log('novel', 300);
        $plan = $this->goals->correct('novel', 120);

        self::assertSame(120, $plan->written);
        self::assertSame(500, $this->goals->correct('novel', 500, Day::fromString('2026-11-01'))->written - 120);
    }

    public function testReachingTheTargetIsToldOnce(): void
    {
        $this->goals->log('novel', 600, Day::fromString('2026-11-01'));
        $plan = $this->goals->log('novel', 400);

        self::assertTrue($plan->isReached());
        self::assertCount(1, $this->events->events);
        $event = $this->events->events[0];
        self::assertInstanceOf(GoalReached::class, $event);
        self::assertSame(['novel', '2026-11-02'], [$event->goal, $event->day->toString()]);
        self::assertSame('writing-goal:novel', $event->idempotencyKey());
        self::assertTrue($event->author->equals($this->author));

        $this->goals->log('novel', 100);
        self::assertCount(1, $this->events->events);
    }

    public function testFallingBackUnderTheTargetAndReachingItAgain(): void
    {
        $this->goals->log('novel', 1000);
        $this->goals->correct('novel', 999);
        $this->goals->correct('novel', 1000);

        self::assertCount(2, $this->events->events);
    }

    public function testAnUnknownGoal(): void
    {
        $this->expectException(UnknownGoal::class);
        $this->expectExceptionMessageIsOrContains('No goal has the identifier "missing".');

        $this->goals->plan('missing');
    }

    public function testAnUnknownGoalIsNotLogged(): void
    {
        try {
            $this->goals->log('missing', 10);
            self::fail('The goal is unknown.');
        } catch (UnknownGoal) {
        }

        self::assertSame([], $this->logs->progress('missing')->toArray());
    }

    public function testAChangedGoalKeepsWhatWasLogged(): void
    {
        $this->goals->log('novel', 100);
        $this->goals->save(new Goal('novel', $this->author, 2000, Day::fromString('2026-11-01'), Day::fromString('2026-11-20'), timezone: 'America/Montreal'));

        self::assertSame([100, 2000], [$this->goals->plan('novel')->written, $this->goals->goal('novel')->target]);
    }

    public function testErasingAnAuthor(): void
    {
        $other = ResourceReference::of('member', '2');
        $this->goals->save(new Goal('poems', $this->author, 10, Day::fromString('2026-11-01'), Day::fromString('2026-11-10')));
        $this->goals->save(new Goal('other', $other, 10, Day::fromString('2026-11-01'), Day::fromString('2026-11-10')));
        $this->goals->log('novel', 100);
        $this->goals->log('other', 5);

        self::assertSame(['novel', 'poems'], array_map(static fn (Goal $goal): string => $goal->id, $this->goals->goalsOf($this->author)));
        self::assertSame(2, $this->goals->erase($this->author));
        self::assertSame([], $this->goals->goalsOf($this->author));
        self::assertSame([], $this->logs->progress('novel')->toArray());
        self::assertSame(5, $this->goals->plan('other')->written);
        self::assertSame(0, $this->goals->erase($this->author));
    }
}
