<?php

declare(strict_types=1);

namespace Tilttek\RpgCalendarEvents\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\RpgCalendarEvents\Catalog;
use Tilttek\RpgCalendarEvents\DataCheck;
use Tilttek\RpgCalendarEvents\Entry;
use Tilttek\RpgCalendarEvents\EntryReader;
use Tilttek\RpgCalendarEvents\Filter;
use Tilttek\RpgCalendarEvents\Occurrence;
use Tilttek\RpgCalendarEvents\ShippedData;

/**
 * The shipped data: every row is an entry with its source and date of verification, no identifier twice, and every
 * entry computed once a year over common and leap years.
 */
#[CoversClass(ShippedData::class)]
#[CoversClass(DataCheck::class)]
final class DataTest extends TestCase
{
    public function testTheShippedDataHasNoProblem(): void
    {
        self::assertSame([], new DataCheck()->problems(ShippedData::rows()));
    }

    public function testTheVersionIsADay(): void
    {
        self::assertMatchesRegularExpression('/^\d{4}\.\d{2}\.\d{2}$/D', ShippedData::version());
        self::assertSame(\dirname(__DIR__, 2).'/data', ShippedData::directory());
    }

    public function testEveryEntryWasVerifiedOnOrBeforeTheVersion(): void
    {
        $version = new DateTimeImmutable(str_replace('.', '-', ShippedData::version()), new DateTimeZone('UTC'));

        foreach (ShippedData::entries() as $entry) {
            self::assertLessThanOrEqual($version, $entry->verified, $entry->id);
            self::assertStringStartsWith('https://', $entry->source, $entry->id);
            self::assertNotSame($entry->names['en'], '', $entry->id);
        }
    }

    public function testEveryEntryFallsOnceAYear(): void
    {
        $entries = ShippedData::entries();
        $catalog = new Catalog($entries, new DateTimeZone('Europe/Paris'));

        // 2024 and 2028 are leap years; 2100 is not.
        foreach ([2023, 2024, 2025, 2026, 2027, 2028, 2029, 2030, 2100] as $year) {
            $ids = array_map(static fn (Occurrence $occurrence): string => $occurrence->entry->id, $catalog->year($year));
            sort($ids);
            $expected = array_map(static fn (Entry $entry): string => $entry->id, $entries);
            sort($expected);

            self::assertSame($expected, $ids, (string) $year);
        }
    }

    public function testTheRulesGiveTheirKnownDates(): void
    {
        $catalog = Catalog::shipped(new DateTimeZone('America/Toronto'));
        $known = [
            'gm-day' => ['2024' => '2024-03-04', '2026' => '2026-03-04'],
            'rpgaday' => ['2024' => '2024-08-01', '2026' => '2026-08-01', '2028' => '2028-08-01'],
            'nagademon' => ['2026' => '2026-11-01'],
        ];

        foreach ($known as $id => $years) {
            foreach ($years as $year => $date) {
                $found = array_values(array_filter($catalog->year($year), static fn (Occurrence $occurrence): bool => $id === $occurrence->entry->id));

                self::assertCount(1, $found, $id);
                self::assertSame($date, $found[0]->start()->format('Y-m-d'), $id.' '.$year);
            }
        }

        self::assertSame('2026-08-31', $catalog->entry('rpgaday')->definition()->spans(2026, new DateTimeZone('America/Toronto'))[0]->end->format('Y-m-d'));
        self::assertSame('2026-11-30', $catalog->entry('nagademon')->definition()->spans(2026, new DateTimeZone('America/Toronto'))[0]->end->format('Y-m-d'));
        self::assertTrue($catalog->entry('rpgaday')->prompts);
        self::assertFalse($catalog->entry('nagademon')->prompts);
    }

    public function testAFifthWeekdayAMonthDoesNotHaveGivesNothing(): void
    {
        $catalog = Catalog::shipped(new DateTimeZone('America/Toronto'), [
            new EntryReader()->read([
                'id' => 'fifth-saturday',
                'names' => ['en' => 'Fifth Saturday of February', 'fr' => 'Cinquième samedi de février'],
                'rule' => '5th saturday of february',
                'source' => 'https://example.org/fifth',
                'community' => 'local',
                'verified' => '2026-11-21',
                'tags' => ['local'],
            ]),
        ]);
        $local = new Filter(['local']);

        // February 2020 and 2048 start on a Saturday and have 29 days; February 2026 and 2024 do not.
        self::assertSame('2020-02-29', $catalog->year(2020, $local)[0]->start()->format('Y-m-d'));
        self::assertSame('2048-02-29', $catalog->year(2048, $local)[0]->start()->format('Y-m-d'));
        self::assertSame([], $catalog->year(2024, $local));
        self::assertSame([], $catalog->year(2026, $local));
    }

    public function testTheCheckListsEveryProblem(): void
    {
        $row = ShippedData::rows()[0];
        $id = $row['id'] ?? null;
        self::assertIsString($id);

        self::assertSame([
            'Row 2 is not an array.',
            'Row 3: The entry "?" has no "rule" text.',
            'Row 4: The entry "'.$id.'" has a rule that cannot be read: "sometime" is not a rule the almanac reads.',
            'Row 5: the identifier "'.$id.'" is already row 1.',
            'Row 6: the identifier "'.$id.'" is already row 1.',
        ], new DataCheck()->problems([$row, 'text', ['names' => []], ['rule' => 'sometime'] + $row, $row, $row]));
        self::assertSame([], new DataCheck()->problems([]));
    }
}
