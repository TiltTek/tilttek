<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar\Tests\Unit;

use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\AdventCalendar\Calendar;
use Tilttek\AdventCalendar\Exception\InvalidCalendar;
use Tilttek\AdventCalendar\OpeningPolicy;
use Tilttek\AdventCalendar\OpeningWindow;
use Tilttek\AdventCalendar\Surprise;

#[CoversClass(Calendar::class)]
#[CoversClass(OpeningWindow::class)]
#[CoversClass(InvalidCalendar::class)]
final class CalendarTest extends TestCase
{
    public function testACalendarOfTwentyFourBoxes(): void
    {
        $calendar = $this->calendar(24);

        self::assertSame('advent-2026', $calendar->id);
        self::assertSame('Europe/Paris', $calendar->timezone->getName());
        self::assertSame(24, $calendar->days());
        self::assertSame('2026-12-01', $calendar->firstDate);
        self::assertSame('2026-12-24', $calendar->closingDate);
        self::assertSame('2026-12-01', $calendar->dateOf(1));
        self::assertSame('2026-12-24', $calendar->dateOf(24));
        self::assertSame('badge-1', $calendar->surprise(1)->reference);
        self::assertSame('badge-24', $calendar->surprise(24)->reference);
        self::assertSame('2026-12-25T00:00:00+01:00', $calendar->closesAt->format(DateTimeInterface::ATOM));
    }

    public function testTheDefaultPolicyKeepsAPastBoxOpenableUntilClosing(): void
    {
        $calendar = $this->calendar(24, closingDate: '2026-12-31');

        self::assertSame('2026-12-31', $calendar->closingDate);
        self::assertSame(['2026-12-03T00:00:00+01:00', '2027-01-01T00:00:00+01:00'], $this->bounds($calendar->window(3)));
        self::assertSame(['2026-12-24T00:00:00+01:00', '2027-01-01T00:00:00+01:00'], $this->bounds($calendar->window(24)));
    }

    public function testTheSameDayPolicy(): void
    {
        $calendar = $this->calendar(24, OpeningPolicy::sameDay(), '2026-12-31');

        self::assertSame(['2026-12-03T00:00:00+01:00', '2026-12-04T00:00:00+01:00'], $this->bounds($calendar->window(3)));
    }

    public function testACatchUpNeverPassesTheClosing(): void
    {
        $calendar = $this->calendar(24, OpeningPolicy::catchUp(3));

        self::assertSame(['2026-12-03T00:00:00+01:00', '2026-12-07T00:00:00+01:00'], $this->bounds($calendar->window(3)));
        self::assertSame(['2026-12-21T00:00:00+01:00', '2026-12-25T00:00:00+01:00'], $this->bounds($calendar->window(21)));
        self::assertSame(['2026-12-22T00:00:00+01:00', '2026-12-25T00:00:00+01:00'], $this->bounds($calendar->window(22)));
    }

    public function testADayOfTwentyFiveHoursWhenTheClocksGoBack(): void
    {
        $calendar = Calendar::of('fall', 'America/Toronto', '2026-10-31', [Surprise::badge('a'), Surprise::badge('b'), Surprise::badge('c')], OpeningPolicy::sameDay());
        $window = $calendar->window(2);

        self::assertSame(['2026-11-01T00:00:00-04:00', '2026-11-02T00:00:00-05:00'], $this->bounds($window));
        self::assertSame(25 * 3600, $window->until->getTimestamp() - $window->from->getTimestamp());
        self::assertSame(2, $calendar->dayAt(new DateTimeImmutable('2026-11-01 23:59:59', $calendar->timezone)));
        self::assertSame(3, $calendar->dayAt(new DateTimeImmutable('2026-11-02 05:00:00 UTC')));
        self::assertSame(2, $calendar->dayAt(new DateTimeImmutable('2026-11-02 04:59:59 UTC')));
    }

    public function testADayOfTwentyThreeHoursWhenTheClocksGoForward(): void
    {
        $calendar = Calendar::of('spring', 'America/Toronto', '2026-03-07', [Surprise::badge('a'), Surprise::badge('b')], OpeningPolicy::sameDay());
        $window = $calendar->window(2);

        self::assertSame(['2026-03-08T00:00:00-05:00', '2026-03-09T00:00:00-04:00'], $this->bounds($window));
        self::assertSame(23 * 3600, $window->until->getTimestamp() - $window->from->getTimestamp());
        self::assertSame('2026-03-09T00:00:00-04:00', $calendar->closesAt->format(DateTimeInterface::ATOM));
    }

    public function testADayWhoseMidnightIsSkippedStartsAtItsFirstMoment(): void
    {
        $calendar = Calendar::of('havana', 'America/Havana', '2026-03-07', [Surprise::badge('a'), Surprise::badge('b')], OpeningPolicy::sameDay());

        self::assertSame(['2026-03-08T01:00:00-04:00', '2026-03-09T00:00:00-04:00'], $this->bounds($calendar->window(2)));
    }

    public function testTheDayAMomentFallsOnInTheCalendarsTimezone(): void
    {
        $calendar = $this->calendar(24);

        self::assertNull($calendar->dayAt(new DateTimeImmutable('2026-11-30 22:59:59 UTC')));
        self::assertSame(1, $calendar->dayAt(new DateTimeImmutable('2026-11-30 23:00:00 UTC')));
        self::assertSame(24, $calendar->dayAt(new DateTimeImmutable('2026-12-24 22:59:59 UTC')));
        self::assertNull($calendar->dayAt(new DateTimeImmutable('2026-12-24 23:00:00 UTC')));
        self::assertFalse($calendar->isClosed(new DateTimeImmutable('2026-12-24 22:59:59 UTC')));
        self::assertTrue($calendar->isClosed(new DateTimeImmutable('2026-12-24 23:00:00 UTC')));
    }

    public function testAWindow(): void
    {
        $window = new OpeningWindow(new DateTimeImmutable('2026-12-01 00:00 UTC'), new DateTimeImmutable('2026-12-02 00:00 UTC'));

        self::assertFalse($window->contains(new DateTimeImmutable('2026-11-30 23:59:59 UTC')));
        self::assertTrue($window->contains(new DateTimeImmutable('2026-12-01 00:00:00 UTC')));
        self::assertTrue($window->contains(new DateTimeImmutable('2026-12-01 23:59:59 UTC')));
        self::assertFalse($window->contains(new DateTimeImmutable('2026-12-02 00:00:00 UTC')));
        self::assertTrue($window->hasEnded(new DateTimeImmutable('2026-12-02 00:00:00 UTC')));
        self::assertFalse($window->hasStarted(new DateTimeImmutable('2026-11-30 23:59:59 UTC')));
    }

    public function testTheClosingMayComeUpTo366DaysAfterTheLastBox(): void
    {
        self::assertSame('2027-12-24', $this->calendar(24, closingDate: '2027-12-24')->closingDate);
        self::assertSame('2027-12-02', Calendar::of('one', 'UTC', '2026-12-01', [Surprise::badge('a')], closingDate: '2027-12-02')->closingDate);
        self::assertSame('2026-12-01', Calendar::of('one', 'UTC', '2026-12-01', [Surprise::badge('a')])->closingDate);
        self::assertSame(92, $this->calendar(92)->days());
    }

    /**
     * @param list<Surprise> $surprises
     */
    #[DataProvider('refusals')]
    public function testWhatACalendarRefuses(string $id, string $timezone, string $firstDate, array $surprises, ?string $closingDate, string $message): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains($message);

        Calendar::of($id, $timezone, $firstDate, $surprises, closingDate: $closingDate);
    }

    /**
     * @return iterable<string, array{string, string, string, list<Surprise>, ?string, string}>
     */
    public static function refusals(): iterable
    {
        $one = [Surprise::badge('a')];

        yield 'an upper-case identifier' => ['Advent', 'UTC', '2026-12-01', $one, null, 'A calendar identifier is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit; "Advent" is not.'];
        yield 'an identifier starting with a dash' => ['-advent', 'UTC', '2026-12-01', $one, null, '"-advent" is not.'];
        yield 'an identifier of 65 characters' => [str_repeat('a', 65), 'UTC', '2026-12-01', $one, null, 'is not.'];
        yield 'an empty identifier' => ['', 'UTC', '2026-12-01', $one, null, '"" is not.'];
        yield 'a line feed after the identifier' => ["advent\n", 'UTC', '2026-12-01', $one, null, 'is not.'];
        yield 'an unknown timezone' => ['advent', 'Mars/Olympus', '2026-12-01', $one, null, '"Mars/Olympus" is not a timezone.'];
        yield 'no box' => ['advent', 'UTC', '2026-12-01', [], null, 'A calendar holds 1 to 92 boxes; 0 were given.'];
        yield '93 boxes' => ['advent', 'UTC', '2026-12-01', array_fill(0, 93, Surprise::badge('a')), null, 'A calendar holds 1 to 92 boxes; 93 were given.'];
        yield 'a date that does not exist' => ['advent', 'UTC', '2026-02-30', $one, null, '"2026-02-30" is not a date written YYYY-MM-DD.'];
        yield 'a date without its leading zero' => ['advent', 'UTC', '2026-12-1', $one, null, '"2026-12-1" is not a date written YYYY-MM-DD.'];
        yield 'a date with a time' => ['advent', 'UTC', '2026-12-01 10:00', $one, null, 'is not a date written YYYY-MM-DD.'];
        yield 'a closing before the last box' => ['advent', 'UTC', '2026-12-01', [Surprise::badge('a'), Surprise::badge('b')], '2026-12-01', 'A calendar closes on the date of its last box or up to 366 days after it; 2026-12-01 is not one of those dates.'];
        yield 'a closing 367 days after the last box' => ['advent', 'UTC', '2026-12-01', $one, '2027-12-03', '2027-12-03 is not one of those dates.'];
        yield 'a closing that is not a date' => ['advent', 'UTC', '2026-12-01', $one, 'soon', '"soon" is not a date written YYYY-MM-DD.'];
    }

    public function testABoxOutsideTheCalendar(): void
    {
        $calendar = $this->calendar(24);

        foreach ([0, 25] as $day) {
            try {
                $calendar->surprise($day);
                self::fail('The box should be refused.');
            } catch (InvalidCalendar $exception) {
                self::assertSame(\sprintf('The calendar "advent-2026" has boxes 1 to 24; %d is not one.', $day), $exception->getMessage());
            }
        }

        $this->expectException(InvalidCalendar::class);
        $calendar->window(25);
    }

    public function testTheLimitsOfACalendar(): void
    {
        self::assertSame(str_repeat('a', 64), Calendar::of(str_repeat('a', 64), 'UTC', '2026-12-01', [Surprise::badge('a')])->id);
        self::assertSame('9.a_b-c', Calendar::of('9.a_b-c', 'UTC', '2026-12-01', [Surprise::badge('a')])->id);
        self::assertSame(92, Calendar::MAX_DAYS);
        self::assertSame(366, Calendar::MAX_DAYS_AFTER_LAST_BOX);
        self::assertSame('1969-12-31', Calendar::of('old', 'UTC', '1969-12-30', [Surprise::badge('a'), Surprise::badge('b')])->dateOf(2));
    }

    private function calendar(int $days, ?OpeningPolicy $policy = null, ?string $closingDate = null): Calendar
    {
        $surprises = [];

        for ($day = 1; $day <= $days; ++$day) {
            $surprises[] = Surprise::badge('badge-'.$day);
        }

        return Calendar::of('advent-2026', 'Europe/Paris', '2026-12-01', $surprises, $policy, $closingDate);
    }

    /**
     * @return array{string, string}
     */
    private function bounds(OpeningWindow $window): array
    {
        return [$window->from->format(DateTimeInterface::ATOM), $window->until->format(DateTimeInterface::ATOM)];
    }
}
