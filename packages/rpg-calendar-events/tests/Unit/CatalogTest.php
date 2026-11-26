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
use Tilttek\RpgCalendarEvents\Exception\InvalidEntry;
use Tilttek\RpgCalendarEvents\Exception\InvalidQuery;
use Tilttek\RpgCalendarEvents\Filter;
use Tilttek\RpgCalendarEvents\Occurrence;
use Tilttek\RpgCalendarEvents\ShippedData;
use Tilttek\RpgCalendarEvents\Status;

#[CoversClass(Catalog::class)]
#[CoversClass(Filter::class)]
#[CoversClass(Occurrence::class)]
#[CoversClass(InvalidQuery::class)]
final class CatalogTest extends TestCase
{
    private DateTimeZone $zone;

    protected function setUp(): void
    {
        $this->zone = new DateTimeZone('America/Toronto');
    }

    public function testItGivesTheDatesOfAYearByStartThenName(): void
    {
        $catalog = $this->catalog();

        self::assertSame([
            '2026-03-14 pi',
            '2026-05-25 geek',
            '2026-05-25 towel',
            '2026-07-31 sysadmin',
            '2026-10-04 space',
        ], $this->list($catalog->year(2026)));
        self::assertSame(['2024-03-14 pi', '2024-05-25 geek', '2024-05-25 towel', '2024-07-26 sysadmin', '2024-10-04 space'], $this->list($catalog->year(2024)));
    }

    public function testAnOccurrenceCarriesItsEntrySpanAndSource(): void
    {
        $space = $this->catalog()->year(2026)[4];

        self::assertSame('space', $space->entry->id);
        self::assertSame('2026-10-04 00:00 EDT', $space->start()->format('Y-m-d H:i T'));
        self::assertSame('2026-10-10 00:00 EDT', $space->end()->format('Y-m-d H:i T'));
        self::assertSame($space->span->start, $space->start());
        self::assertSame($space->span->end, $space->end());
        self::assertSame('https://example.org/space', $space->source());
        self::assertSame(7, $space->span->days());
    }

    public function testItGivesTheDatesOfAPeriodItsEndLeftOut(): void
    {
        $catalog = $this->catalog();

        self::assertSame(['2026-05-25 geek', '2026-05-25 towel'], $this->list($catalog->between(new DateTimeImmutable('2026-05-25', $this->zone), new DateTimeImmutable('2026-07-31', $this->zone))));
        self::assertSame(['2026-10-04 space'], $this->list($catalog->between(new DateTimeImmutable('2026-10-10', $this->zone), new DateTimeImmutable('2026-10-11', $this->zone))));
        self::assertSame([], $catalog->between(new DateTimeImmutable('2026-10-11', $this->zone), new DateTimeImmutable('2026-12-31', $this->zone)));
        self::assertSame(['2026-10-04 space', '2027-03-14 pi'], $this->list($catalog->between(new DateTimeImmutable('2026-10-05', $this->zone), new DateTimeImmutable('2027-04-01', $this->zone))));
    }

    public function testItGivesTheDatesOfADayInItsTimeZone(): void
    {
        $catalog = $this->catalog();

        self::assertSame(['2026-05-25 geek', '2026-05-25 towel'], $this->list($catalog->on(new DateTimeImmutable('2026-05-25 23:59', $this->zone))));
        self::assertSame(['2026-10-04 space'], $this->list($catalog->on(new DateTimeImmutable('2026-10-07 12:00', $this->zone))));
        // 03:00 in UTC is still May 24 in Toronto.
        self::assertSame([], $catalog->on(new DateTimeImmutable('2026-05-25 03:00', new DateTimeZone('UTC'))));
        self::assertSame(['2026-05-25 geek', '2026-05-25 towel'], $this->list($catalog->on(new DateTimeImmutable('2026-05-26 03:00', new DateTimeZone('UTC')))));
        self::assertSame([], $catalog->on(new DateTimeImmutable('2026-05-26', $this->zone)));
        self::assertSame([], $catalog->on(new DateTimeImmutable('2026-05-24 12:00', $this->zone)));
    }

    public function testItFiltersByTagCommunityAndVerification(): void
    {
        $catalog = $this->catalog();

        self::assertSame(['2026-03-14 pi', '2026-10-04 space'], $this->list($catalog->year(2026, new Filter(tags: ['science']))));
        self::assertSame(['2026-05-25 towel', '2026-07-31 sysadmin'], $this->list($catalog->year(2026, new Filter(communities: ['readers', 'admins']))));
        self::assertSame(['2026-05-25 towel'], $this->list($catalog->year(2026, new Filter(tags: ['books', 'science'], communities: ['readers']))));
        self::assertSame(['2026-03-14 pi', '2026-05-25 geek', '2026-05-25 towel', '2026-07-31 sysadmin'], $this->list($catalog->year(2026, new Filter(verifiedOnly: true))));
        self::assertSame(['2026-03-14 pi'], $this->list($catalog->on(new DateTimeImmutable('2026-03-14', $this->zone), new Filter(tags: ['science']))));
        self::assertSame([], $catalog->on(new DateTimeImmutable('2026-03-14', $this->zone), new Filter(communities: ['readers'])));
    }

    public function testAFilterOnNothingIsRefused(): void
    {
        $this->expectException(InvalidQuery::class);
        $this->expectExceptionMessageIsOrContains('A filter on no tag or no community reads nothing: leave it out instead.');

        new Filter(tags: []);
    }

    public function testAFilterOnNoCommunityIsRefused(): void
    {
        $this->expectException(InvalidQuery::class);

        new Filter(communities: []);
    }

    public function testAFilterAcceptsAnEntry(): void
    {
        $towel = $this->catalog()->entry('towel');

        self::assertTrue(new Filter()->accepts($towel));
        self::assertTrue(new Filter(['books'], ['readers'], true)->accepts($towel));
        self::assertFalse(new Filter(['science'])->accepts($towel));
        self::assertFalse(new Filter(communities: ['admins'])->accepts($towel));
        self::assertFalse(new Filter(verifiedOnly: true)->accepts($this->catalog()->entry('space')));
    }

    public function testItHoldsItsEntriesByIdentifier(): void
    {
        $catalog = $this->catalog();

        self::assertSame(['pi', 'towel', 'geek', 'sysadmin', 'space'], array_keys($catalog->entries()));
        self::assertTrue($catalog->has('towel'));
        self::assertFalse($catalog->has('tea'));
        self::assertSame('towel', $catalog->entry('towel')->id);
        self::assertSame($this->zone, $catalog->zone());
    }

    public function testAnUnknownEntryIsRefused(): void
    {
        $this->expectException(InvalidQuery::class);
        $this->expectExceptionMessageIsOrContains('The catalog holds no entry "t'.str_repeat('é', 99).'".');

        $this->catalog()->entry('t'.str_repeat('é', 119));
    }

    public function testAnApplicationAddsItsOwnEntries(): void
    {
        $catalog = $this->catalog();
        $local = $catalog->with($this->entry('local-con', '3rd saturday of june', ['convention'], 'montreal'), $this->entry('jam', '06-01', ['convention']));

        self::assertFalse($catalog->has('local-con'));
        self::assertSame(['pi', 'towel', 'geek', 'sysadmin', 'space', 'local-con', 'jam'], array_keys($local->entries()));
        self::assertSame(['2026-06-01 jam', '2026-06-20 local-con'], $this->list($local->year(2026, new Filter(['convention']))));
        self::assertSame($this->zone, $local->zone());
    }

    public function testTheShippedCatalogTakesTheApplicationsEntries(): void
    {
        $catalog = Catalog::shipped($this->zone, [$this->entry('local-con', '3rd saturday of june')]);

        self::assertTrue($catalog->has('gm-day'));
        self::assertTrue($catalog->has('local-con'));
        self::assertSame('local-con', array_key_last($catalog->entries()));
        self::assertCount(\count(ShippedData::entries()) + 1, $catalog->entries());
        self::assertTrue(Catalog::shipped($this->zone)->has('gm-day'));
    }

    public function testAnIdentifierTwiceIsRefused(): void
    {
        $this->expectException(InvalidEntry::class);
        $this->expectExceptionMessageIsOrContains('The entry "towel" is in the catalog twice.');

        $this->catalog()->with($this->entry('towel', '05-25'));
    }

    public function testAnIdentifierTwiceInTheConstructorIsRefused(): void
    {
        $this->expectException(InvalidEntry::class);
        $this->expectExceptionMessageIsOrContains('The entry "a" is in the catalog twice.');

        new Catalog([$this->entry('a', '01-01'), $this->entry('a', '01-02')], $this->zone);
    }

    public function testTheYearsReadAreBounded(): void
    {
        $catalog = $this->catalog();

        self::assertCount(5, $catalog->year(1000));
        self::assertCount(5, $catalog->year(3000));
        self::assertCount(5, $catalog->between(new DateTimeImmutable('1000-01-01', $this->zone), new DateTimeImmutable('1001-01-01', $this->zone)));
        self::assertCount(5, $catalog->between(new DateTimeImmutable('3000-01-01', $this->zone), new DateTimeImmutable('3001-01-01', $this->zone)));

        foreach ([999, 3001] as $year) {
            try {
                $catalog->year($year);
                self::fail('No exception for '.$year);
            } catch (InvalidQuery $invalidQuery) {
                self::assertSame(\sprintf('The catalog reads the years 1000 to 3000, not %d.', $year), $invalidQuery->getMessage());
            }
        }

        $message = 'The catalog reads the years 1000 to 3000: a period starts on 1000-01-01 or later and ends on 3001-01-01 or earlier.';

        foreach ([['0999-12-31 23:59', '1000-02-01'], ['3000-12-01', '3001-01-01 00:01']] as [$from, $until]) {
            try {
                $catalog->between(new DateTimeImmutable($from, $this->zone), new DateTimeImmutable($until, $this->zone));
                self::fail('No exception for '.$from);
            } catch (InvalidQuery $invalidQuery) {
                self::assertSame($message, $invalidQuery->getMessage());
            }
        }
    }

    private function catalog(): Catalog
    {
        return new Catalog([
            $this->entry('pi', '03-14', ['science', 'mathematics']),
            $this->entry('towel', '05-25', ['books'], 'readers'),
            $this->entry('geek', '05-25'),
            $this->entry('sysadmin', 'last friday of july', [], 'admins'),
            $this->entry('space', '10-04', ['science'], days: 7, status: Status::ToVerify),
        ], $this->zone);
    }

    /**
     * @param list<string> $tags
     */
    private function entry(string $id, string $rule, array $tags = [], string $community = 'worldwide', int $days = 1, Status $status = Status::Verified): Entry
    {
        return new Entry($id, ['en' => ucfirst($id), 'fr' => ucfirst($id)], RuleParser::parse($rule), 'https://example.org/'.$id, $community, new DateTimeImmutable('2026-11-21'), $tags, $days, $status);
    }

    /**
     * @param list<Occurrence> $occurrences
     *
     * @return list<string>
     */
    private function list(array $occurrences): array
    {
        return array_map(static fn (Occurrence $occurrence): string => $occurrence->start()->format('Y-m-d').' '.$occurrence->entry->id, $occurrences);
    }
}
