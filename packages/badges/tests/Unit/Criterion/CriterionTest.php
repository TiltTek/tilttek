<?php

declare(strict_types=1);

namespace Tilttek\Badges\Tests\Unit\Criterion;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Badges\Criterion\AtLeast;
use Tilttek\Badges\Criterion\Count;
use Tilttek\Badges\Criterion\Manual;
use Tilttek\Badges\Criterion\Measure;
use Tilttek\Badges\Criterion\Scope;
use Tilttek\Badges\Criterion\Streak;
use Tilttek\Badges\Criterion\Total;
use Tilttek\Badges\Exception\InvalidBadge;
use Tilttek\Badges\Tests\Unit\Fixture\Read;

#[CoversClass(Scope::class)]
#[CoversClass(Count::class)]
#[CoversClass(Total::class)]
#[CoversClass(Streak::class)]
#[CoversClass(Measure::class)]
#[CoversClass(Manual::class)]
#[CoversClass(AtLeast::class)]
final class CriterionTest extends TestCase
{
    public function testAScopeReadsItsVerbsContextAndPeriod(): void
    {
        $scope = new Scope(['game.*', 'news.commented', 'game.*'], ['system' => 'dnd5e'], $this->at('2026-11-01 00:00:00'), $this->at('2026-12-01 00:00:00'));

        self::assertSame(['game.*', 'news.commented'], $scope->patterns);
        self::assertSame(['system' => 'dnd5e'], $scope->conditions->values);
        self::assertTrue($scope->accepts($this->activity('game.played', '2026-11-01 00:00:00', ['system' => 'dnd5e', 'other' => 1])));
        self::assertTrue($scope->accepts($this->activity('game.won', '2026-11-30 23:59:59', ['system' => 'dnd5e'])));
        self::assertFalse($scope->accepts($this->activity('game.played', '2026-10-31 23:59:59', ['system' => 'dnd5e'])));
        self::assertFalse($scope->accepts($this->activity('game.played', '2026-12-01 00:00:00', ['system' => 'dnd5e'])));
        self::assertFalse($scope->accepts($this->activity('game.played', '2026-11-10 00:00:00', ['system' => 'pf2e'])));
        self::assertFalse($scope->accepts($this->activity('game.played', '2026-11-10 00:00:00')));
        self::assertFalse($scope->accepts($this->activity('book.read', '2026-11-10 00:00:00', ['system' => 'dnd5e'])));

        self::assertSame(['a', 'b'], new Scope(['a', 'a', 'b'])->patterns);
        self::assertNotNull(new Scope(['a'], [], $this->at('2026-11-01 00:00:00'))->since);
        self::assertNotNull(new Scope(['a'], [], null, $this->at('2026-11-01 00:00:00'))->until);

        $open = new Scope(['game.played']);
        self::assertNull($open->since);
        self::assertNull($open->until);
        self::assertTrue($open->accepts($this->activity('game.played', '1999-01-01 00:00:00')));
    }

    public function testAScopeReadsAtLeastOneVerb(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('A criterion reads at least one verb.'));
        new Scope([]);
    }

    public function testAScopeRefusesABadVerb(): void
    {
        $this->expectException(InvalidBadge::class);
        $this->expectExceptionMessageIsOrContains('"Game" is not a verb');
        new Scope(['Game']);
    }

    public function testAScopeRefusesABadContext(): void
    {
        $this->expectException(InvalidBadge::class);
        $this->expectExceptionMessageIsOrContains('is not a context entry name');
        new Scope(['game.played'], ['Bad' => 1]);
    }

    public function testAScopePeriodEndsAfterItStarts(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The period of a criterion ends after it starts.'));
        new Scope(['game.played'], [], $this->at('2026-11-01 00:00:00'), $this->at('2026-11-01 00:00:00'));
    }

    public function testACountCountsActivities(): void
    {
        $count = new Count(new Scope(['news.commented']), 10);

        self::assertSame(['news.commented'], $count->patterns());
        self::assertSame(['r' => $count], $count->leaves('r'));
        self::assertTrue($count->accepts($this->activity('news.commented', '2026-11-01 00:00:00')));
        self::assertFalse($count->accepts($this->activity('game.played', '2026-11-01 00:00:00')));
        $progress = $count->progress('r.1', new Read(['r.1' => 7, 'r' => 99]));
        self::assertSame([7, 10], [$progress->current, $progress->target]);
        self::assertSame(1, new Count(new Scope(['a']))->times);
        self::assertSame(Count::MAX, new Count(new Scope(['a']), Count::MAX)->times);
    }

    public function testACountIsOneOrMore(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('A count is 1 to 1000000000 activities; this one is 0.'));
        new Count(new Scope(['a']), 0);
    }

    public function testACountHasALimit(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('A count is 1 to 1000000000 activities; this one is 1000000001.'));
        new Count(new Scope(['a']), Count::MAX + 1);
    }

    public function testATotalSumsQuantities(): void
    {
        $total = new Total(new Scope(['book.read']), 1000);

        self::assertSame(['book.read'], $total->patterns());
        self::assertSame(['r.0' => $total], $total->leaves('r.0'));
        self::assertTrue($total->accepts($this->activity('book.read', '2026-11-01 00:00:00')));
        self::assertFalse($total->accepts($this->activity('book.closed', '2026-11-01 00:00:00')));
        $progress = $total->progress('r', new Read(['r' => 3], ['r' => 640]));
        self::assertSame([640, 1000], [$progress->current, $progress->target]);
        self::assertSame(1, new Total(new Scope(['a']), 1)->amount);
        self::assertSame(Total::MAX, new Total(new Scope(['a']), Total::MAX)->amount);
    }

    public function testATotalIsOneOrMore(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('A total is 1 to 1000000000000; this one is 0.'));
        new Total(new Scope(['a']), 0);
    }

    public function testATotalHasALimit(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('A total is 1 to 1000000000000; this one is 1000000000001.'));
        new Total(new Scope(['a']), Total::MAX + 1);
    }

    public function testAStreakCountsTheLongestRunOfDays(): void
    {
        $streak = new Streak(new Scope(['site.visited']), 3, new DateTimeZone('America/Toronto'));

        self::assertSame(['site.visited'], $streak->patterns());
        self::assertSame(['r' => $streak], $streak->leaves('r'));
        self::assertTrue($streak->accepts($this->activity('site.visited', '2026-11-01 00:00:00')));
        self::assertFalse($streak->accepts($this->activity('site.left', '2026-11-01 00:00:00')));
        self::assertSame('UTC', new Streak(new Scope(['a']), 2)->timezone->getName());

        // In Toronto: 1, 2 (twice), 3, then 5, 6, 7, 8 November — 04:59 UTC on the 2nd is still the 1st.
        $moments = array_map($this->at(...), ['2026-11-05 12:00:00', '2026-11-01 12:00:00', '2026-11-02 04:59:00', '2026-11-02 12:00:00', '2026-11-02 23:00:00', '2026-11-03 12:00:00', '2026-11-06 12:00:00', '2026-11-07 12:00:00', '2026-11-08 12:00:00', '2026-11-09 12:00:00']);
        $progress = $streak->progress('r', new Read(moments: ['r' => $moments]));
        self::assertSame([5, 3], [$progress->current, $progress->target]);
        self::assertSame(0, $streak->progress('r', new Read())->current);
        self::assertSame(1, $streak->progress('r', new Read(moments: ['r' => [$this->at('2026-11-05 12:00:00'), $this->at('2026-11-05 13:00:00')]]))->current);
        self::assertSame(2, $streak->progress('r', new Read(moments: ['r' => [$this->at('2026-12-31 12:00:00'), $this->at('2027-01-01 12:00:00')]]))->current);
        self::assertSame(3, $streak->progress('r', new Read(moments: ['r' => [$this->at('2026-11-12 12:00:00'), $this->at('2026-11-11 12:00:00'), $this->at('2026-11-11 13:00:00'), $this->at('2026-11-10 12:00:00')]]))->current);
        self::assertSame(2, $streak->progress('r', new Read(moments: ['r' => [$this->at('2026-03-08 12:00:00'), $this->at('2026-03-09 12:00:00'), $this->at('2026-11-20 12:00:00')]]))->current);
    }

    public function testAStreakIsTwoDaysOrMore(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('A streak is 2 to 3660 days; this one is 1.'));
        new Streak(new Scope(['a']), 1);
    }

    public function testAStreakHasALimit(): void
    {
        self::assertSame(Streak::MAX_DAYS, new Streak(new Scope(['a']), Streak::MAX_DAYS)->days);
        $this->expectExceptionObject(InvalidBadge::because('A streak is 2 to 3660 days; this one is 3661.'));
        new Streak(new Scope(['a']), Streak::MAX_DAYS + 1);
    }

    public function testAMeasureReadsTheApplicationsValue(): void
    {
        $measure = new Measure(new Scope(['game.*']), 'points', 1000);

        self::assertSame(['game.*'], $measure->patterns());
        self::assertSame(['r' => $measure], $measure->leaves('r'));
        self::assertTrue($measure->accepts($this->activity('game.won', '2026-11-01 00:00:00')));
        self::assertFalse($measure->accepts($this->activity('news.commented', '2026-11-01 00:00:00')));
        $progress = $measure->progress('r', new Read(measures: ['points' => 1200]));
        self::assertSame([1200, 1000], [$progress->current, $progress->target]);
        self::assertSame(0, $measure->progress('r', new Read(measures: ['points' => -5]))->current);
        self::assertSame(1, new Measure(new Scope(['a']), 'p', 1)->threshold);
    }

    public function testAMeasureIsNamed(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('"Points" is not a measure name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.'));
        new Measure(new Scope(['a']), 'Points', 1);
    }

    public function testAMeasureNameEndsWithTheName(): void
    {
        $this->expectException(InvalidBadge::class);
        new Measure(new Scope(['a']), "points\n", 1);
    }

    public function testAMeasureNameHasALimit(): void
    {
        self::assertSame(64, \strlen(new Measure(new Scope(['a']), 'a'.str_repeat('b', 63), 1)->name));
        $this->expectException(InvalidBadge::class);
        new Measure(new Scope(['a']), 'a'.str_repeat('b', 64), 1);
    }

    public function testAMeasureHasAThreshold(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The threshold of a measure is at least 1; 0 is not.'));
        new Measure(new Scope(['a']), 'points', 0);
    }

    public function testAManualCriterionIsNeverReachedByActivity(): void
    {
        $manual = new Manual();

        self::assertSame([], $manual->leaves('r'));
        $progress = $manual->progress('r', new Read(['r' => 50]));
        self::assertSame([0, 1], [$progress->current, $progress->target]);
    }

    public function testACombinationCountsTheCriteriaReached(): void
    {
        $first = new Count(new Scope(['game.played']));
        $second = new Count(new Scope(['news.commented']), 2);
        $third = new Total(new Scope(['book.read']), 10);
        $all = new AtLeast([$first, new AtLeast([$second, $third])]);

        self::assertSame(2, $all->required);
        self::assertSame(['r.0' => $first, 'r.1.0' => $second, 'r.1.1' => $third], $all->leaves('r'));
        self::assertSame([1, 2], [$all->progress('r', new Read(['r.0' => 1, 'r.1.0' => 2]))->current, 2]);
        self::assertSame(2, $all->progress('r', new Read(['r.0' => 1, 'r.1.0' => 2], ['r.1.1' => 10]))->current);

        $two = new AtLeast([$first, $second, $third], 2);
        self::assertSame(2, $two->required);
        self::assertSame([$first, $second, $third], $two->criteria);
        $progress = $two->progress('r', new Read(['r.0' => 1], ['r.2' => 12]));
        self::assertSame([2, 2], [$progress->current, $progress->target]);
        self::assertSame(1, new AtLeast([$first, $second], 1)->required);
    }

    public function testACombinationHoldsACriterion(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('A combination holds at least one criterion.'));
        new AtLeast([]);
    }

    public function testACombinationRequiresAtLeastOne(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('A combination of 2 criteria requires 1 to 2 of them; this one requires 0.'));
        new AtLeast([new Manual(), new Manual()], 0);
    }

    public function testACombinationRequiresAtMostAll(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('A combination of 2 criteria requires 1 to 2 of them; this one requires 3.'));
        new AtLeast([new Manual(), new Manual()], 3);
    }

    /**
     * @param array<string, bool|int|string> $context
     */
    private function activity(string $verb, string $at, array $context = []): Activity
    {
        return Activity::of('k', 'member:1', $verb, $this->at($at), context: $context);
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('UTC'));
    }
}
