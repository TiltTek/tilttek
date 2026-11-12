<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Tests\Unit;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\WritingGoals\Curve;
use Tilttek\WritingGoals\CurveShape;
use Tilttek\WritingGoals\Day;
use Tilttek\WritingGoals\Exception\InvalidGoal;
use Tilttek\WritingGoals\Goal;
use Tilttek\WritingGoals\Progress;
use Tilttek\WritingGoals\Unit;
use Tilttek\WritingGoals\WritingDays;

#[CoversClass(Goal::class)]
#[CoversClass(Progress::class)]
#[CoversClass(Unit::class)]
final class GoalAndProgressTest extends TestCase
{
    public function testAGoalAndItsDefaults(): void
    {
        $author = ResourceReference::of('member', '1');
        $goal = new Goal('novel-1', $author, 50_000, Day::fromString('2026-11-01'), Day::fromString('2026-11-30'));

        self::assertSame(['novel-1', 50_000, '2026-11-01', '2026-11-30', Unit::Words, CurveShape::Linear, 'UTC'], [$goal->id, $goal->target, $goal->start->toString(), $goal->deadline->toString(), $goal->unit, $goal->curve->shape, $goal->timezone->getName()]);
        self::assertTrue($goal->author->equals($author));
        self::assertSame([1, 2, 3, 4, 5, 6, 7], $goal->writingDays->weekdays);
        self::assertCount(30, $goal->writingDaysOfPlan());
        self::assertSame('2026-11-01', $goal->writingDaysOfPlan()[0]->toString());
        self::assertSame('2026-11-30', $goal->writingDaysOfPlan()[29]->toString());
    }

    public function testAGoalOnSomeDays(): void
    {
        $goal = new Goal('A1.b:c_d-e', ResourceReference::of('member', '1'), 300, Day::fromString('2026-11-02'), Day::fromString('2026-11-15'), Unit::Pages, Curve::backLoaded(), WritingDays::on(1, 3, 5)->except(Day::fromString('2026-11-04')), 'America/Montreal');

        self::assertSame(['2026-11-02', '2026-11-06', '2026-11-09', '2026-11-11', '2026-11-13'], array_map(static fn (Day $day): string => $day->toString(), $goal->writingDaysOfPlan()));
        self::assertSame([Unit::Pages, CurveShape::BackLoaded, 'America/Montreal'], [$goal->unit, $goal->curve->shape, $goal->timezone->getName()]);
        self::assertCount(1, new Goal('one-day', ResourceReference::of('member', '1'), 1, Day::fromString('2026-11-02'), Day::fromString('2026-11-02'))->writingDaysOfPlan());
        self::assertSame(Goal::MAX_TARGET, new Goal(str_repeat('a', 128), ResourceReference::of('member', '1'), 1_000_000_000, Day::fromString('2026-01-01'), Day::fromString('2026-01-01')->plus(3659))->target);
        self::assertSame([1_000_000_000, 3660], [Goal::MAX_TARGET, Goal::MAX_DAYS]);
    }

    #[DataProvider('refusedGoals')]
    public function testWhatAGoalRefuses(string $id, int $target, string $start, string $deadline, string $timezone, ?WritingDays $writingDays, string $message): void
    {
        $this->expectException(InvalidGoal::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Goal($id, ResourceReference::of('member', '1'), $target, Day::fromString($start), Day::fromString($deadline), writingDays: $writingDays, timezone: $timezone);
    }

    /**
     * @return iterable<string, array{string, int, string, string, string, ?WritingDays, string}>
     */
    public static function refusedGoals(): iterable
    {
        yield 'an empty identifier' => ['', 1, '2026-11-01', '2026-11-30', 'UTC', null, 'A goal identifier is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter or a digit; "" is not.'];
        yield 'an identifier with a space' => ['a b', 1, '2026-11-01', '2026-11-30', 'UTC', null, '"a b" is not.'];
        yield 'an identifier starting with a dash' => ['-a', 1, '2026-11-01', '2026-11-30', 'UTC', null, '"-a" is not.'];
        yield 'an identifier of 129 characters' => [str_repeat('a', 129), 1, '2026-11-01', '2026-11-30', 'UTC', null, 'is not.'];
        yield 'a line feed after the identifier' => ["a\n", 1, '2026-11-01', '2026-11-30', 'UTC', null, 'is not.'];
        yield 'a target of 0' => ['a', 0, '2026-11-01', '2026-11-30', 'UTC', null, 'A target is 1 to 1000000000; 0 is not one.'];
        yield 'a target too high' => ['a', 1_000_000_001, '2026-11-01', '2026-11-30', 'UTC', null, 'A target is 1 to 1000000000; 1000000001 is not one.'];
        yield 'a deadline before the start' => ['a', 1, '2026-11-01', '2026-10-31', 'UTC', null, 'A goal lasts 1 to 3660 days, its first and last included; from 2026-11-01 to 2026-10-31 is not one.'];
        yield 'a goal of 3661 days' => ['a', 1, '2026-01-01', '2036-01-09', 'UTC', null, 'from 2026-01-01 to 2036-01-09 is not one.'];
        yield 'an unknown timezone' => ['a', 1, '2026-11-01', '2026-11-30', 'Mars/Olympus', null, '"Mars/Olympus" is not a timezone.'];
        yield 'no writing day' => ['a', 1, '2026-11-02', '2026-11-03', 'UTC', WritingDays::on(6, 7), 'A goal from 2026-11-02 to 2026-11-03 has no writing day.'];
    }

    public function testTheProgressOfAGoal(): void
    {
        $progress = Progress::fromArray(['2026-11-03' => 500, '2026-11-01' => 1000]);
        $later = $progress->with(Day::fromString('2026-11-02'), 250)->with(Day::fromString('2026-11-03'), 600);

        self::assertSame(['2026-11-01' => 1000, '2026-11-03' => 500], $progress->toArray());
        self::assertSame(['2026-11-01' => 1000, '2026-11-02' => 250, '2026-11-03' => 600], $later->toArray());
        self::assertSame(1500, $progress->total());
        self::assertSame(1850, $later->total());
        self::assertSame(1250, $later->before(Day::fromString('2026-11-03')));
        self::assertSame(0, $later->before(Day::fromString('2026-11-01')));
        self::assertSame(1000, $later->before(Day::fromString('2026-11-02')));
        self::assertSame(600, $later->on(Day::fromString('2026-11-03')));
        self::assertSame(0, $later->on(Day::fromString('2026-11-04')));
        self::assertSame([], Progress::none()->toArray());
        self::assertSame(0, Progress::none()->total());
        self::assertSame(Goal::MAX_TARGET, Progress::none()->with(Day::fromString('2026-11-01'), Progress::MAX_PER_DAY)->total());
        self::assertSame(0, Progress::none()->with(Day::fromString('2026-11-01'), 0)->total());
    }

    #[DataProvider('refusedCounts')]
    public function testWhatAProgressRefuses(int $count): void
    {
        $this->expectException(InvalidGoal::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A day counts 0 to 1000000000; %d is not one.', $count));

        Progress::none()->with(Day::fromString('2026-11-01'), $count);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedCounts(): iterable
    {
        yield 'negative' => [-1];
        yield 'too many' => [1_000_000_001];
    }
}
