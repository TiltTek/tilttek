<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\SeasonalEvents\Content;
use Tilttek\SeasonalEvents\ContentKind;
use Tilttek\SeasonalEvents\Exception\InvalidSeason;
use Tilttek\SeasonalEvents\Season;
use Tilttek\SeasonalEvents\SeasonReader;

#[CoversClass(SeasonReader::class)]
final class SeasonReaderTest extends TestCase
{
    public function testTheSampleSeasonsAreReadAndWrittenBackAsTheyWere(): void
    {
        $definitions = $this->samples();
        $seasons = SeasonReader::all($definitions);

        self::assertSame(['winter-festival', 'spooky-season', 'harvest-feast', 'spring-cleaning-2027'], array_map(static fn (Season $season): string => $season->id, $seasons));

        $winter = $seasons[0];
        self::assertTrue($winter->schedule->isYearly());
        self::assertSame(23, $winter->schedule->days);
        self::assertCount(2, $winter->events);
        self::assertSame(200, $winter->event('gift-week')?->multiplier?->percent);
        self::assertSame(7, $winter->event('gift-week')->announceDays);
        self::assertSame(8, $winter->event('gift-week')->fromDay);
        self::assertSame(7, $winter->event('gift-week')->days);
        self::assertSame(ContentKind::Item, $winter->event('gift-week')->contents[0]->kind);
        self::assertSame(4, $seasons[1]->event('masquerade')?->days);

        foreach ($seasons as $season) {
            self::assertEquals($season, SeasonReader::season(SeasonReader::write($season)));
        }
    }

    public function testASeasonIsWrittenWithItsDefaultsAndWithoutEmptyLists(): void
    {
        $season = SeasonReader::season(['id' => 'once', 'timezone' => 'UTC', 'once' => ['from' => '2026-01-01', 'through' => '2026-01-03'], 'events' => [['id' => 'all']]]);

        self::assertSame([
            'id' => 'once',
            'timezone' => 'UTC',
            'once' => ['from' => '2026-01-01', 'through' => '2026-01-03'],
            'events' => [['id' => 'all', 'from_day' => 1, 'announce_days' => 0]],
        ], SeasonReader::write($season));
        self::assertSame(1, $season->events[0]->fromDay);
        self::assertNull($season->events[0]->days);
        self::assertSame(0, $season->events[0]->announceDays);
        self::assertSame([], $season->contents);
        $yearly = SeasonReader::write(SeasonReader::season(['id' => 'y', 'timezone' => 'UTC', 'yearly' => ['from' => '2026-01-01', 'days' => 2]]))['yearly'];
        self::assertIsArray($yearly);
        self::assertSame('FREQ=YEARLY', $yearly['rule']);
    }

    public function testAHundredSeasonsAreRead(): void
    {
        $seasons = array_map(static fn (int $i): array => ['id' => 's'.$i, 'timezone' => 'UTC', 'once' => ['from' => '2026-01-01', 'through' => '2026-01-02']], range(1, 100));

        self::assertCount(100, SeasonReader::all($seasons));
    }

    public function testTheContentsAreReadInTheOrderQuestsItemsDecorations(): void
    {
        $season = SeasonReader::season(['id' => 's', 'timezone' => 'UTC', 'once' => ['from' => '2026-01-01', 'through' => '2026-01-01'], 'decorations' => ['decoration:a'], 'items' => ['item:b'], 'quests' => ['quest:c']]);

        self::assertSame(['quest:c', 'item:b', 'decoration:a'], array_map(static fn (Content $content): string => $content->reference->toString(), $season->contents));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function unreadable(): iterable
    {
        $once = ['from' => '2026-01-01', 'through' => '2026-01-02'];
        $season = ['id' => 's', 'timezone' => 'UTC', 'once' => $once];

        yield 'seasons not in a list' => [['a' => $season], 'The seasons are a list of definitions.'];
        yield 'too many seasons' => [array_fill(0, 101, $season), 'A list holds at most 100 seasons; 101 were given.'];
        yield 'a season not an array' => [['winter'], 'A season is written as an array.'];
        yield 'an unknown key' => [[[...$season, 'colour' => 'red']], 'A season has no key "colour"; its keys are "id", "timezone", "once", "yearly", "quests", "items", "decorations", "events".'];
        yield 'no identifier' => [[['timezone' => 'UTC', 'once' => $once]], 'A season has a text "id".'];
        yield 'no timezone' => [[['id' => 's', 'once' => $once]], 'The season "s" has a text "timezone".'];
        yield 'neither once nor yearly' => [[['id' => 's', 'timezone' => 'UTC']], 'The season "s" comes "once" or "yearly", one of the two.'];
        yield 'both once and yearly' => [[[...$season, 'yearly' => ['from' => '2026-01-01', 'days' => 3]]], 'The season "s" comes "once" or "yearly", one of the two.'];
        yield 'once not an array' => [[[...$season, 'once' => '2026']], 'The "once" of a season is written as an array.'];
        yield 'once with an unknown key' => [[[...$season, 'once' => [...$once, 'days' => 2]]], 'A "once" has no key "days"'];
        yield 'yearly not an array' => [[['id' => 's', 'timezone' => 'UTC', 'yearly' => 3]], 'The "yearly" of a season is written as an array.'];
        yield 'yearly with an unknown key' => [[['id' => 's', 'timezone' => 'UTC', 'yearly' => ['from' => '2026-01-01', 'days' => 3, 'through' => 'x']]], 'A "yearly" has no key "through"'];
        yield 'yearly days as text' => [[['id' => 's', 'timezone' => 'UTC', 'yearly' => ['from' => '2026-01-01', 'days' => '3']]], 'A season has a whole number "days".'];
        yield 'yearly rule not a text' => [[['id' => 's', 'timezone' => 'UTC', 'yearly' => ['from' => '2026-01-01', 'days' => 3, 'rule' => 3]]], 'A season has a text "rule".'];
        yield 'events not an array' => [[[...$season, 'events' => 'all']], 'The events of a season is written as an array.'];
        yield 'events not a list' => [[[...$season, 'events' => ['a' => ['id' => 'a']]]], 'The events of a season are a list.'];
        yield 'an event not an array' => [[[...$season, 'events' => ['a']]], 'An event is written as an array.'];
        yield 'an event with an unknown key' => [[[...$season, 'events' => [['id' => 'a', 'until' => 3]]]], 'A event has no key "until"'];
        yield 'an event day as text' => [[[...$season, 'events' => [['id' => 'a', 'from_day' => '1']]]], 'An event has a whole number "from_day".'];
        yield 'an event length as text' => [[[...$season, 'events' => [['id' => 'a', 'days' => '1']]]], 'An event has a whole number "days".'];
        yield 'an announcement as text' => [[[...$season, 'events' => [['id' => 'a', 'announce_days' => '1']]]], 'An event has a whole number "announce_days".'];
        yield 'a multiplier not an array' => [[[...$season, 'events' => [['id' => 'a', 'multiplier' => 2]]]], 'The multiplier of an event is written as an array.'];
        yield 'a multiplier with an unknown key' => [[[...$season, 'events' => [['id' => 'a', 'multiplier' => ['ledger' => 'p', 'percent' => 200, 'cap' => 3]]]]], 'A multiplier has no key "cap"'];
        yield 'a multiplier without ledger' => [[[...$season, 'events' => [['id' => 'a', 'multiplier' => ['percent' => 200]]]]], 'A multiplier has a text "ledger".'];
        yield 'a multiplier without percent' => [[[...$season, 'events' => [['id' => 'a', 'multiplier' => ['ledger' => 'p']]]]], 'A multiplier has a whole number "percent".'];
        yield 'quests not an array' => [[[...$season, 'quests' => 'quest:a']], 'The quests is written as an array.'];
        yield 'items not a list' => [[[...$season, 'items' => ['a' => 'item:a']]], 'The items are a list of references.'];
        yield 'a decoration not a text' => [[[...$season, 'decorations' => [3]]], 'The decorations are references written "kind:identifier".'];
        yield 'a reference without kind' => [[[...$season, 'quests' => ['hunt']]], '"hunt" is not a resource reference of the form "kind:identifier".'];
        yield 'a numeric key' => [[[...$season, 0 => 'x']], 'A season has no key "0"'];
    }

    #[DataProvider('unreadable')]
    public function testAnUnreadableDefinitionIsRefusedWithTheLibrarysException(mixed $definitions, string $message): void
    {
        $this->expectException(InvalidSeason::class);
        $this->expectExceptionMessageIsOrContains($message);

        SeasonReader::all(\is_array($definitions) ? $definitions : []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function samples(): array
    {
        /** @var list<array<string, mixed>> $definitions */
        $definitions = require \dirname(__DIR__, 2).'/resources/sample-seasons.php';

        return $definitions;
    }
}
