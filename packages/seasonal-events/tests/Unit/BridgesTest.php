<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\Collections\Catalog;
use Tilttek\Collections\FixedCatalog;
use Tilttek\Collections\Item;
use Tilttek\Collections\Rarity;
use Tilttek\Collections\Texts;
use Tilttek\Quests\Schedule\Occurrence;
use Tilttek\SeasonalEvents\Bridge\Collections\SeasonalItems;
use Tilttek\SeasonalEvents\Bridge\Quests\SeasonQuestSchedule;
use Tilttek\SeasonalEvents\Content;
use Tilttek\SeasonalEvents\Season;
use Tilttek\SeasonalEvents\SeasonalEvent;
use Tilttek\SeasonalEvents\SeasonalEvents;
use Tilttek\SeasonalEvents\SeasonSchedule;
use Tilttek\SeasonalEvents\Store\InMemorySeasonCatalogue;

#[CoversClass(SeasonQuestSchedule::class)]
#[CoversClass(SeasonalItems::class)]
final class BridgesTest extends TestCase
{
    private const string ZONE = 'America/Toronto';

    public function testAQuestOpensWithEachTimeOfItsSeason(): void
    {
        $season = new Season('winter', SeasonSchedule::yearly(self::ZONE, '2026-12-15', 23));
        $schedule = new SeasonQuestSchedule($season);

        self::assertNull($schedule->occurrenceAt($this->at('2026-12-14 23:59')));

        $occurrence = $schedule->occurrenceAt($this->at('2027-01-02 00:00'));
        self::assertInstanceOf(Occurrence::class, $occurrence);
        self::assertSame('2026-12-15', $occurrence->code);
        self::assertSame('2026-12-15T00:00:00-05:00', $occurrence->opensAt?->format(\DATE_ATOM));
        self::assertSame('2027-01-07T00:00:00-05:00', $occurrence->closesAt?->format(\DATE_ATOM));
        self::assertSame('2027-12-15', $schedule->occurrenceAt($this->at('2027-12-15 00:00'))?->code);
    }

    public function testAQuestOpensWithAnEventOfItsSeason(): void
    {
        $event = new SeasonalEvent('gift-week', 8, 7);
        $season = new Season('winter', SeasonSchedule::yearly(self::ZONE, '2026-12-15', 23), [], [$event]);
        $schedule = new SeasonQuestSchedule($season, $event);

        self::assertNull($schedule->occurrenceAt($this->at('2026-12-21 23:59')));
        self::assertNull($schedule->occurrenceAt($this->at('2026-12-01 00:00')));

        $occurrence = $schedule->occurrenceAt($this->at('2026-12-22 00:00'));
        self::assertInstanceOf(Occurrence::class, $occurrence);
        self::assertSame('2026-12-15', $occurrence->code);
        self::assertSame('2026-12-22T00:00:00-05:00', $occurrence->opensAt?->format(\DATE_ATOM));
        self::assertSame('2026-12-29T00:00:00-05:00', $occurrence->closesAt?->format(\DATE_ATOM));
    }

    public function testTheItemsOfACollectionTheSeasonsBringAreListed(): void
    {
        $season = new Season('winter', SeasonSchedule::once(self::ZONE, '2026-12-01', '2026-12-31'), [
            Content::item('item:snow-hat'),
            Content::item('item:missing'),
            Content::item('card:snow-hat'),
            Content::item('item:mitten'),
        ]);
        $seasons = new SeasonalEvents(new InMemorySeasonCatalogue([$season]), new MockClock($this->at('2026-12-10 00:00')));
        $catalog = new FixedCatalog(new Catalog(
            [new Rarity('common', 10)],
            [new Item('mitten', Texts::of(['en' => 'Mitten']), 'common'), new Item('snow-hat', Texts::of(['en' => 'Snow hat']), 'common'), new Item('sun-hat', Texts::of(['en' => 'Sun hat']), 'common')],
        ));

        self::assertSame(['snow-hat', 'mitten'], array_map(static fn (Item $item): string => $item->code, new SeasonalItems($seasons, $catalog)->at()));
        self::assertSame(['snow-hat'], array_map(static fn (Item $item): string => $item->code, new SeasonalItems($seasons, $catalog, 'card')->at()));
        self::assertSame([], new SeasonalItems($seasons, $catalog)->at($this->at('2027-01-01 00:00')));
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone(self::ZONE));
    }
}
