<?php

declare(strict_types=1);

namespace Tilttek\RpgCalendarEvents\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Almanac\Rule\RuleParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\RpgCalendarEvents\Catalog;
use Tilttek\RpgCalendarEvents\Entry;
use Tilttek\RpgCalendarEvents\Filter;
use Tilttek\RpgCalendarEvents\PromptDay;

#[CoversClass(PromptDay::class)]
#[CoversClass(Catalog::class)]
final class PromptDayTest extends TestCase
{
    private DateTimeZone $zone;

    protected function setUp(): void
    {
        $this->zone = new DateTimeZone('America/Toronto');
    }

    public function testADayOfAThemedMonthHasItsIndex(): void
    {
        $catalog = $this->catalog();

        $first = $catalog->promptDays(new DateTimeImmutable('2026-08-01 00:00', $this->zone));
        self::assertCount(1, $first);
        self::assertSame('month', $first[0]->occurrence->entry->id);
        self::assertSame(1, $first[0]->index);
        self::assertSame(31, $first[0]->length());
        self::assertTrue($first[0]->isFirst());
        self::assertFalse($first[0]->isLast());

        $middle = $catalog->promptDays(new DateTimeImmutable('2026-08-15 18:30', $this->zone))[0];
        self::assertSame(15, $middle->index);
        self::assertFalse($middle->isFirst());
        self::assertFalse($middle->isLast());

        $last = $catalog->promptDays(new DateTimeImmutable('2026-08-31 23:59', $this->zone))[0];
        self::assertSame(31, $last->index);
        self::assertTrue($last->isLast());
        self::assertFalse($last->isFirst());
    }

    public function testTheIndexCountsCalendarDaysAcrossAChangeOfTime(): void
    {
        // Toronto leaves daylight saving time on 2026-11-01: a day of 25 hours in the period.
        $catalog = $this->catalog();

        self::assertSame(2, $catalog->promptDays(new DateTimeImmutable('2026-11-02', $this->zone))[0]->index);
        self::assertSame(30, $catalog->promptDays(new DateTimeImmutable('2026-11-30 23:00', $this->zone))[0]->index);
        // Toronto enters daylight saving time on 2027-03-14.
        self::assertSame(14, $catalog->promptDays(new DateTimeImmutable('2027-03-14 12:00', $this->zone))[0]->index);
    }

    public function testTheDayIsReadInTheCatalogsTimeZone(): void
    {
        $catalog = $this->catalog();

        self::assertSame([], $catalog->promptDays(new DateTimeImmutable('2026-08-01 03:00', new DateTimeZone('UTC'))));
        self::assertSame(1, $catalog->promptDays(new DateTimeImmutable('2026-08-02 03:00', new DateTimeZone('UTC')))[0]->index);
        self::assertSame(31, $catalog->promptDays(new DateTimeImmutable('2026-09-01 03:00', new DateTimeZone('UTC')))[0]->index);
    }

    public function testOnlyThePeriodsWithPromptsHaveDays(): void
    {
        $catalog = $this->catalog();

        self::assertSame([], $catalog->promptDays(new DateTimeImmutable('2026-10-15', $this->zone)));
        self::assertCount(1, $catalog->on(new DateTimeImmutable('2026-10-15', $this->zone)));
        self::assertSame([], $catalog->promptDays(new DateTimeImmutable('2026-09-01', $this->zone)));
    }

    public function testThePromptDaysAreFiltered(): void
    {
        $catalog = $this->catalog()->with(new Entry('other', ['en' => 'Other', 'fr' => 'Autre'], RuleParser::parse('08-10'), 'https://example.org/other', 'larp', new DateTimeImmutable('2026-11-21'), days: 5, prompts: true));
        $day = new DateTimeImmutable('2026-08-12', $this->zone);

        self::assertSame(['month 12', 'other 3'], array_map(static fn (PromptDay $prompt): string => $prompt->occurrence->entry->id.' '.$prompt->index, $catalog->promptDays($day)));
        self::assertSame(['other 3'], array_map(static fn (PromptDay $prompt): string => $prompt->occurrence->entry->id.' '.$prompt->index, $catalog->promptDays($day, new Filter(communities: ['larp']))));
    }

    private function catalog(): Catalog
    {
        $verified = new DateTimeImmutable('2026-11-21');

        return new Catalog([
            new Entry('month', ['en' => 'Month', 'fr' => 'Mois'], RuleParser::parse('08-01'), 'https://example.org/month', 'rpg', $verified, days: 31, prompts: true),
            new Entry('design', ['en' => 'Design', 'fr' => 'Création'], RuleParser::parse('10-01'), 'https://example.org/design', 'rpg', $verified, days: 31),
            new Entry('november', ['en' => 'November', 'fr' => 'Novembre'], RuleParser::parse('11-01'), 'https://example.org/november', 'rpg', $verified, days: 30, prompts: true),
            new Entry('march', ['en' => 'March', 'fr' => 'Mars'], RuleParser::parse('03-01'), 'https://example.org/march', 'rpg', $verified, days: 31, prompts: true),
        ], $this->zone);
    }
}
