<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar\Tests\Unit;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\FantasyCalendar\CalendarCodec;
use Tilttek\FantasyCalendar\Exception\InvalidCalendar;
use Tilttek\FantasyCalendar\Template\Gregorian;

#[CoversClass(CalendarCodec::class)]
#[CoversClass(InvalidCalendar::class)]
final class CalendarCodecTest extends TestCase
{
    public function testACalendarIsWrittenAndReadBack(): void
    {
        $data = CalendarCodec::toArray(CalendarTest::world());

        self::assertSame($data, CalendarCodec::toArray(CalendarCodec::fromArray($data)));
        self::assertSame(['id' => 'midfeast', 'name' => 'Midfeast', 'days' => 0, 'leapDays' => 1, 'inWeek' => false], $data['months'][2]);
        self::assertSame(['every' => 9, 'offset' => 1, 'leap' => false], $data['leap'][1]);
        self::assertSame(['id' => 'red', 'name' => 'Red', 'cycle' => '12.5', 'newMoon' => 0], $data['moons'][1]);
        self::assertSame(['id' => 'before', 'name' => 'Before the Founding', 'start' => null], $data['eras'][0]);
        self::assertSame(['id' => 'warm', 'name' => 'Warm', 'month' => 'bloom', 'day' => 1], $data['seasons'][1]);
        self::assertSame('temperate', $data['climateZone']);
    }

    public function testDefaultsAndOtherFormsOfACycle(): void
    {
        $calendar = CalendarCodec::fromArray([
            'id' => 'c',
            'name' => 'C',
            'months' => [['id' => 'm', 'name' => 'M', 'days' => 10]],
            'weekdays' => ['D'],
            'moons' => [['id' => 'a', 'name' => 'A', 'cycle' => 9], ['id' => 'b', 'name' => 'B', 'cycle' => [10, 3], 'newMoon' => 2]],
        ]);

        self::assertSame(0, $calendar->months[0]->leapDays);
        self::assertTrue($calendar->months[0]->inWeek);
        self::assertSame(0, $calendar->firstWeekday);
        self::assertSame([], $calendar->leapRule->cycles);
        self::assertSame([], $calendar->eras);
        self::assertSame([], $calendar->seasons);
        self::assertNull($calendar->climateZone);
        self::assertSame(0, $calendar->moons[0]->newMoon);
        self::assertSame('9', CalendarCodec::toArray($calendar)['moons'][0]['cycle']);
        self::assertSame([10, 3], CalendarCodec::toArray($calendar)['moons'][1]['cycle']);
        self::assertSame(2, $calendar->moons[1]->newMoon);
    }

    public function testLeapCycleDefaults(): void
    {
        $data = Gregorian::definition();
        $data['leap'] = [['every' => 4]];

        $cycle = CalendarCodec::fromArray($data)->leapRule->cycles[0];

        self::assertSame([4, 0, true], [$cycle->every, $cycle->offset, $cycle->leap]);
    }

    /**
     * @return iterable<string, array{Closure(array<string, mixed>): array<array-key, mixed>, string}>
     */
    public static function refusals(): iterable
    {
        yield 'unknown key' => [static fn (array $d): array => [...$d, 'holidays' => []], 'holidays: is not a key of a calendar.'];
        yield 'no id' => [static fn (array $d): array => array_diff_key($d, ['id' => true]), 'calendar.id: is a text.'];
        yield 'name not a text' => [static fn (array $d): array => [...$d, 'name' => 5], 'calendar.name: is a text.'];
        yield 'no months' => [static fn (array $d): array => array_diff_key($d, ['months' => true]), 'months: is a list.'];
        yield 'months not a list' => [static fn (array $d): array => [...$d, 'months' => ['a' => []]], 'months: is a list.'];
        yield 'month not an object' => [static fn (array $d): array => [...$d, 'months' => [[1, 2]]], 'months.0: is an object.'];
        yield 'month not an array' => [static fn (array $d): array => [...$d, 'months' => ['x']], 'months.0: is an object.'];
        yield 'month key' => [static fn (array $d): array => [...$d, 'months' => [['id' => 'm', 'name' => 'M', 'days' => 1, 'weeks' => 1]]], 'months.0.weeks: is not a key here.'];
        yield 'month days' => [static fn (array $d): array => [...$d, 'months' => [['id' => 'm', 'name' => 'M', 'days' => '1']]], 'months.0.days: is a whole number.'];
        yield 'month in week' => [static fn (array $d): array => [...$d, 'months' => [['id' => 'm', 'name' => 'M', 'days' => 1, 'inWeek' => 1]]], 'months.0.inWeek: is true or false.'];
        yield 'month bounds' => [static fn (array $d): array => [...$d, 'months' => [['id' => 'm', 'name' => 'M', 'days' => 5000]]], 'months.0: The days of a month lies from 0 to 1000; it is 5000.'];
        yield 'empty month' => [static fn (array $d): array => [...$d, 'months' => [[]]], 'months.0.id: is a text.'];
        yield 'weekday' => [static fn (array $d): array => [...$d, 'weekdays' => ['Monday', 2]], 'weekdays.1: is a text.'];
        yield 'leap not a list' => [static fn (array $d): array => [...$d, 'leap' => 'every 4'], 'leap: is a list.'];
        yield 'leap cycle' => [static fn (array $d): array => [...$d, 'leap' => [['every' => 0]]], 'leap.0: The interval of a leap cycle lies from 1 to 10000; it is 0.'];
        yield 'leap period' => [static fn (array $d): array => [...$d, 'leap' => [['every' => 101], ['every' => 103]]], 'leap: The leap cycles repeat within 10000 years.'];
        yield 'moon cycle' => [static fn (array $d): array => [...$d, 'moons' => [['id' => 'm', 'name' => 'M', 'cycle' => true]]], 'moons.0.cycle: is a decimal number of days as a text, a whole number of days, or [numerator, denominator].'];
        yield 'moon cycle pair' => [static fn (array $d): array => [...$d, 'moons' => [['id' => 'm', 'name' => 'M', 'cycle' => [10]]]], 'moons.0.cycle: is a decimal number'];
        yield 'moon cycle pair of texts' => [static fn (array $d): array => [...$d, 'moons' => [['id' => 'm', 'name' => 'M', 'cycle' => ['10', 3]]]], 'moons.0.cycle: is a decimal number'];
        yield 'moon cycle pair, second text' => [static fn (array $d): array => [...$d, 'moons' => [['id' => 'm', 'name' => 'M', 'cycle' => [10, '3']]]], 'moons.0.cycle: is a decimal number'];
        yield 'moon cycle map' => [static fn (array $d): array => [...$d, 'moons' => [['id' => 'm', 'name' => 'M', 'cycle' => ['a' => 10, 'b' => 3]]]], 'moons.0.cycle: is a decimal number'];
        yield 'moon decimal' => [static fn (array $d): array => [...$d, 'moons' => [['id' => 'm', 'name' => 'M', 'cycle' => 'soon']]], 'moons.0: The cycle of a moon is a decimal number of days'];
        yield 'era start' => [static fn (array $d): array => [...$d, 'eras' => [['id' => 'e', 'name' => 'E', 'start' => '1']]], 'eras.0.start: is a whole number, or null for an era that counts backward.'];
        yield 'era name' => [static fn (array $d): array => [...$d, 'eras' => [['id' => 'e', 'name' => '', 'start' => 1]]], 'eras.0: An era name is a text'];
        yield 'season month' => [static fn (array $d): array => [...$d, 'seasons' => [['id' => 's', 'name' => 'S', 'month' => 'smarch', 'day' => 1]]], 'seasons.0.month: names no month of the calendar ("smarch").'];
        yield 'season day' => [static fn (array $d): array => [...$d, 'seasons' => [['id' => 's', 'name' => 'S', 'month' => 'march', 'day' => 'one']]], 'seasons.0.day: is a whole number.'];
        yield 'season identifier' => [static fn (array $d): array => [...$d, 'seasons' => [['id' => ' ', 'name' => 'S', 'month' => 'march', 'day' => 1]]], 'seasons.0: A season identifier is 1 to 64'];
        yield 'climate zone' => [static fn (array $d): array => [...$d, 'climateZone' => 3], 'climateZone: is a text.'];
        yield 'calendar' => [static fn (array $d): array => [...$d, 'firstWeekday' => 9], 'calendar: The weekday of day 0 lies from 0 to 6; it is 9.'];
    }

    /**
     * @param Closure(array<string, mixed>): array<array-key, mixed> $change
     */
    #[DataProvider('refusals')]
    public function testItRefusesWhatItDoesNotKnow(Closure $change, string $message): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains($message);

        CalendarCodec::fromArray($change(Gregorian::definition()));
    }

    /**
     * @return array<string, mixed>
     */
    private function minimal(): array
    {
        return ['id' => 'c', 'name' => 'C', 'months' => [['id' => 'm', 'name' => 'M', 'days' => 10]], 'weekdays' => ['D']];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function refusal(array $data): string
    {
        try {
            CalendarCodec::fromArray($data);
        } catch (InvalidCalendar $invalidCalendar) {
            return $invalidCalendar->getMessage();
        }

        self::fail('The calendar was read.');
    }

    public function testOnlyMonthsAndWeekdaysAreRequired(): void
    {
        $calendar = CalendarCodec::fromArray($this->minimal());

        self::assertSame([], $calendar->moons);
        self::assertSame([], $calendar->eras);
        self::assertSame([], $calendar->seasons);

        $data = $this->minimal();
        unset($data['weekdays']);
        self::assertSame('weekdays: is a list.', $this->refusal($data));
    }

    public function testAPlaceIsNamedOnceAndWithoutTheEndOfTheReason(): void
    {
        $data = $this->minimal();
        $data['months'] = [['id' => 5, 'name' => 'M', 'days' => 10]];
        self::assertSame('months.0.id: is a text.', $this->refusal($data));

        $data['months'] = [['id' => 'm', 'name' => 'M', 'days' => 0]];
        self::assertSame('months.0: The month "m" has days in a common year or in a leap year.', $this->refusal($data));
    }

    public function testAMonthNamedByASeasonIsQuotedShortened(): void
    {
        $data = $this->minimal();
        $data['seasons'] = [['id' => 's', 'name' => 'S', 'month' => str_repeat('x', 100), 'day' => 1]];

        self::assertSame('seasons.0.month: names no month of the calendar ("'.str_repeat('x', 63).'…").', $this->refusal($data));
    }
}
