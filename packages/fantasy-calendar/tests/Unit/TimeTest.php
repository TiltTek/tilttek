<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\FantasyCalendar\CurrentDate;
use Tilttek\FantasyCalendar\Day;
use Tilttek\FantasyCalendar\Exception\CalendarMismatch;
use Tilttek\FantasyCalendar\Exception\InvalidCalendar;
use Tilttek\FantasyCalendar\Lifespan;
use Tilttek\FantasyCalendar\Pace;

#[CoversClass(CurrentDate::class)]
#[CoversClass(Pace::class)]
#[CoversClass(Lifespan::class)]
final class TimeTest extends TestCase
{
    private const int SET = 1_800_000_000;

    private function at(int $seconds): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.(self::SET + $seconds));
    }

    public function testTheDateMovesAtItsPace(): void
    {
        $daily = new CurrentDate(new Day('w', 100), self::SET, Pace::of(1));

        self::assertSame(100, $daily->today($this->at(0))->number);
        self::assertSame(100, $daily->today($this->at(86_399))->number);
        self::assertSame(101, $daily->today($this->at(86_400))->number);
        self::assertSame(110, $daily->today($this->at(10 * 86_400 + 5))->number);
        self::assertSame(100, $daily->today($this->at(-86_400 * 3))->number);
        self::assertSame('w', $daily->today($this->at(86_400))->calendar);

        $fast = new CurrentDate(new Day('w', 0), self::SET, Pace::of(7, 2));
        self::assertSame(3, $fast->today($this->at(86_400))->number);
        self::assertSame(7, $fast->today($this->at(2 * 86_400))->number);
    }

    public function testAManualDateMovesOnlyWhenMoved(): void
    {
        $manual = new CurrentDate(new Day('w', 5), self::SET, Pace::manual());

        self::assertTrue(Pace::manual()->isManual());
        self::assertFalse(Pace::of(1)->isManual());
        self::assertSame(5, $manual->today($this->at(86_400 * 400))->number);

        $moved = $manual->advancedBy(3, $this->at(100));
        self::assertSame(8, $moved->today($this->at(86_400 * 9))->number);
        self::assertSame(self::SET + 100, $moved->since);

        $set = $moved->setTo(new Day('w', 42), $this->at(200));
        self::assertSame(42, $set->day->number);
        self::assertSame(self::SET + 200, $set->since);
        self::assertTrue($set->pace->isManual());
    }

    public function testMovingAndChangingPaceKeepTheCurrentDay(): void
    {
        $daily = new CurrentDate(new Day('w', 0), self::SET, Pace::of(1));

        $back = $daily->advancedBy(-5, $this->at(2 * 86_400));
        self::assertSame(-3, $back->day->number);
        self::assertSame(1, $back->pace->worldDays);

        $faster = $daily->atPace(Pace::of(10), $this->at(2 * 86_400));
        self::assertSame(2, $faster->day->number);
        self::assertSame(self::SET + 2 * 86_400, $faster->since);
        self::assertSame(12, $faster->today($this->at(3 * 86_400))->number);
    }

    public function testPacesAreBounded(): void
    {
        self::assertSame(1000, Pace::of(1000, 1000)->worldDays);
        self::assertSame(1000, Pace::of(1000, 1000)->realDays);
        self::assertSame(1, Pace::of(3)->realDays);

        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('The world days of a pace lies from 1 to 1000; it is 0.');

        Pace::of(0);
    }

    public function testRealDaysAreBounded(): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('The real days of a pace lies from 1 to 1000; it is 1001.');

        Pace::of(1, 1001);
    }

    public function testAges(): void
    {
        $world = CalendarTest::world();
        $life = new Lifespan($world->day(10, 4, 10), $world->day(50, 2, 1));

        self::assertNull($life->ageOn($world, $world->day(10, 4, 9)));
        self::assertSame(0, $life->ageOn($world, $world->day(10, 4, 10)));
        self::assertSame(19, $life->ageOn($world, $world->day(30, 4, 9)));
        self::assertSame(20, $life->ageOn($world, $world->day(30, 4, 10)));
        self::assertSame(39, $life->ageOn($world, $world->day(50, 2, 1)));
        self::assertSame(39, $life->ageOn($world, $world->day(90, 7, 1)));
        self::assertFalse($life->hasEndedBy($world->day(50, 2, 1)));
        self::assertTrue($life->hasEndedBy($world->day(50, 2, 2)));

        $city = new Lifespan($world->day(-300, 1, 1));
        self::assertSame(310, $city->ageOn($world, $world->day(10, 1, 1)));
        self::assertFalse($city->hasEndedBy($world->day(10, 1, 1)));
        self::assertNull($city->end);
        self::assertSame(0, new Lifespan($world->day(1, 1, 1), $world->day(1, 1, 1))->ageOn($world, $world->day(1, 1, 1)));
    }

    public function testALifespanEndsAfterItStarts(): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('A lifespan ends on or after the day it starts.');

        new Lifespan(new Day('w', 10), new Day('w', 9));
    }

    public function testALifespanStaysInOneCalendar(): void
    {
        $this->expectException(CalendarMismatch::class);

        new Lifespan(new Day('w', 10), new Day('v', 19));
    }

    public function testAManualPaceKeepsOneRealDay(): void
    {
        self::assertSame(0, Pace::manual()->worldDays);
        self::assertSame(1, Pace::manual()->realDays);
    }
}
