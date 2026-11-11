<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit\Season;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Progression\Exception\InvalidProgression;
use Tilttek\Progression\Season\CalendarSeasons;
use Tilttek\Progression\Season\NamedSeasons;
use Tilttek\Progression\Season\Season;
use Tilttek\Progression\Season\SeasonLength;

#[CoversClass(Season::class)]
#[CoversClass(CalendarSeasons::class)]
#[CoversClass(NamedSeasons::class)]
#[CoversClass(SeasonLength::class)]
final class SeasonsTest extends TestCase
{
    public function testASeason(): void
    {
        $season = new Season('summer-2026', new DateTimeImmutable('2026-06-21'), new DateTimeImmutable('2026-09-22'));

        self::assertTrue($season->holds(new DateTimeImmutable('2026-06-21')));
        self::assertTrue($season->holds(new DateTimeImmutable('2026-09-21 23:59:59')));
        self::assertFalse($season->holds(new DateTimeImmutable('2026-09-22')));
        self::assertFalse($season->holds(new DateTimeImmutable('2026-06-20 23:59:59')));
        self::assertSame('2026', new Season('2026', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'))->code);
        self::assertSame(str_repeat('a', 64), new Season(str_repeat('a', 64), new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-01 00:00:01'))->code);
    }

    public function testASeasonCodeIsChecked(): void
    {
        foreach (['', 'Summer', '-a', str_repeat('a', 65), "a\n"] as $code) {
            try {
                new Season($code, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-02-01'));
                self::fail(\sprintf('"%s" was expected to be refused.', $code));
            } catch (InvalidProgression $invalidProgression) {
                self::assertSame(\sprintf('"%s" is not a season code: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit.', $code), $invalidProgression->getMessage());
            }
        }
    }

    public function testASeasonEndsAfterItStarts(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('The season "a" ends after it starts.'));
        new Season('a', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-01'));
    }

    public function testCalendarSeasons(): void
    {
        $toronto = new DateTimeZone('America/Toronto');
        $moment = new DateTimeImmutable('2026-10-01 02:00:00 UTC');

        self::assertSame(['2026-09', '2026-09-01 00:00 -04:00', '2026-10-01 00:00 -04:00'], $this->describe(new CalendarSeasons(SeasonLength::Month, $toronto)->at($moment)));
        self::assertSame(['2026-q3', '2026-07-01 00:00 -04:00', '2026-10-01 00:00 -04:00'], $this->describe(new CalendarSeasons(SeasonLength::Quarter, $toronto)->at($moment)));
        self::assertSame(['2026-q4', '2026-10-01 00:00 +00:00', '2027-01-01 00:00 +00:00'], $this->describe(new CalendarSeasons(SeasonLength::Quarter)->at($moment)));
        self::assertSame(['2026-q4', '2026-10-01 00:00 +00:00', '2027-01-01 00:00 +00:00'], $this->describe(new CalendarSeasons(SeasonLength::Quarter)->at(new DateTimeImmutable('2026-12-31 23:00:00 UTC'))));
        self::assertSame(['2026-q1', '2026-01-01 00:00 +00:00', '2026-04-01 00:00 +00:00'], $this->describe(new CalendarSeasons(SeasonLength::Quarter)->at(new DateTimeImmutable('2026-03-31 23:00:00 UTC'))));
        self::assertSame(['2026-q2', '2026-04-01 00:00 +00:00', '2026-07-01 00:00 +00:00'], $this->describe(new CalendarSeasons(SeasonLength::Quarter)->at(new DateTimeImmutable('2026-04-01 00:00:00 UTC'))));
        self::assertSame(['2026', '2026-01-01 00:00 -05:00', '2027-01-01 00:00 -05:00'], $this->describe(new CalendarSeasons(SeasonLength::Year, $toronto)->at($moment)));
        self::assertSame(['0099-01', '0099-01-01 00:00 +00:00', '0099-02-01 00:00 +00:00'], $this->describe(new CalendarSeasons(SeasonLength::Month)->at(new DateTimeImmutable('0099-01-05'))));
    }

    public function testCalendarSeasonsByCode(): void
    {
        $months = new CalendarSeasons(SeasonLength::Month, new DateTimeZone('America/Toronto'));
        $quarters = new CalendarSeasons(SeasonLength::Quarter);
        $years = new CalendarSeasons(SeasonLength::Year);

        self::assertSame(['2026-11', '2026-11-01 00:00 -04:00', '2026-12-01 00:00 -05:00'], $this->describe($months->named('2026-11')));
        self::assertSame('2026-01', $months->named('2026-01')?->code);
        self::assertSame('2026-12', $months->named('2026-12')?->code);
        self::assertSame(['2026-q4', '2026-10-01 00:00 +00:00', '2027-01-01 00:00 +00:00'], $this->describe($quarters->named('2026-q4')));
        self::assertSame('2026-01-01', $quarters->named('2026-q1')?->start->format('Y-m-d'));
        self::assertSame('2026-04-01', $quarters->named('2026-q2')?->start->format('Y-m-d'));
        self::assertSame('2026-07-01', $quarters->named('2026-q3')?->start->format('Y-m-d'));
        self::assertSame(['2026', '2026-01-01 00:00 +00:00', '2027-01-01 00:00 +00:00'], $this->describe($years->named('2026')));

        foreach (['2026-13', '2026-00', '2026-1', 'x2026-11', '2026-11x', '2026-q4', '2026'] as $code) {
            self::assertNull($months->named($code), $code);
        }

        foreach (['2026-q0', '2026-q5', '2026-11', '2026', 'x2026-q1', '2026-q1x'] as $code) {
            self::assertNull($quarters->named($code), $code);
        }

        foreach (['2026-11', '2026-q1', '26', 'x2026', '2026x'] as $code) {
            self::assertNull($years->named($code), $code);
        }
    }

    public function testNamedSeasons(): void
    {
        $summer = new Season('summer', new DateTimeImmutable('2026-06-21'), new DateTimeImmutable('2026-09-22'));
        $spring = new Season('spring', new DateTimeImmutable('2026-03-20'), new DateTimeImmutable('2026-06-21'));
        $winter = new Season('winter', new DateTimeImmutable('2026-12-21'), new DateTimeImmutable('2027-03-20'));
        $seasons = new NamedSeasons($summer, $winter, $spring);

        self::assertSame(['spring', 'summer', 'winter'], array_keys($seasons->seasons));
        self::assertSame($summer, $seasons->at(new DateTimeImmutable('2026-06-21')));
        self::assertSame($spring, $seasons->at(new DateTimeImmutable('2026-06-20 23:00')));
        self::assertNull($seasons->at(new DateTimeImmutable('2026-10-01')));
        self::assertSame($winter, $seasons->named('winter'));
        self::assertNull($seasons->named('autumn'));
    }

    public function testNamedSeasonsDoNotOverlap(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('The seasons "a" and "b" overlap.'));
        new NamedSeasons(
            new Season('b', new DateTimeImmutable('2026-06-20 23:59:59'), new DateTimeImmutable('2026-09-22')),
            new Season('a', new DateTimeImmutable('2026-03-20'), new DateTimeImmutable('2026-06-21')),
        );
    }

    public function testNamedSeasonsHaveTwoCodes(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('Two seasons are coded "a".'));
        new NamedSeasons(
            new Season('a', new DateTimeImmutable('2026-03-20'), new DateTimeImmutable('2026-06-21')),
            new Season('a', new DateTimeImmutable('2027-03-20'), new DateTimeImmutable('2027-06-21')),
        );
    }

    /**
     * @return list<string>
     */
    private function describe(?Season $season): array
    {
        self::assertInstanceOf(Season::class, $season);

        return [$season->code, $season->start->format('Y-m-d H:i P'), $season->end->format('Y-m-d H:i P')];
    }
}
