<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\SeasonalEvents\ActiveEvent;
use Tilttek\SeasonalEvents\ActiveSeason;
use Tilttek\SeasonalEvents\Content;
use Tilttek\SeasonalEvents\ContentKind;
use Tilttek\SeasonalEvents\Exception\InvalidSeason;
use Tilttek\SeasonalEvents\Multiplier;
use Tilttek\SeasonalEvents\Season;
use Tilttek\SeasonalEvents\SeasonalEvent;
use Tilttek\SeasonalEvents\SeasonalEvents;
use Tilttek\SeasonalEvents\SeasonReader;
use Tilttek\SeasonalEvents\SeasonSchedule;
use Tilttek\SeasonalEvents\Snapshot;
use Tilttek\SeasonalEvents\Store\InMemorySeasonCatalogue;

#[CoversClass(SeasonalEvents::class)]
#[CoversClass(Snapshot::class)]
#[CoversClass(InMemorySeasonCatalogue::class)]
final class SeasonalEventsTest extends TestCase
{
    private const string ZONE = 'America/Toronto';

    public function testWhatIsOnIsReadAtTheClocksMomentByDefault(): void
    {
        $events = $this->sample('2026-12-23 12:00');
        $snapshot = $events->at();

        self::assertSame('2026-12-23T12:00:00-05:00', $snapshot->moment->format(\DATE_ATOM));
        self::assertFalse($snapshot->isQuiet());
        self::assertTrue($snapshot->isOn('winter-festival'));
        self::assertFalse($snapshot->isOn('spooky-season'));
        self::assertSame(['gift-week'], array_map(static fn (ActiveEvent $active): string => $active->event->id, $snapshot->events));
        self::assertSame(['quest:winter-hunt'], $this->strings($snapshot->quests()));
        self::assertSame(['item:wrapped-die'], $this->strings($snapshot->items()));
        self::assertSame(['decoration:snowflakes'], $this->strings($snapshot->decorations()));
        self::assertSame(200, $snapshot->multiplier('points')?->percent);
        self::assertNull($snapshot->multiplier('xp'));
        self::assertSame('winter-festival', $snapshot->toArray()['seasons'][0]['season']);
        self::assertSame('gift-week', $snapshot->toArray()['events'][0]['event']);
        self::assertSame('2026-12-23T12:00:00-05:00', $snapshot->toArray()['moment']);
    }

    public function testAMomentGivenStandsForTheClock(): void
    {
        $events = $this->sample('2026-12-23 12:00');
        $snapshot = $events->at($this->at('2026-10-30 12:00'));

        self::assertTrue($snapshot->isOn('spooky-season'));
        self::assertSame(['masquerade'], array_map(static fn (ActiveEvent $active): string => $active->event->id, $snapshot->events));
        self::assertSame(['decoration:candles', 'decoration:masks'], $this->strings($snapshot->decorations()));
        self::assertTrue($events->at($this->at('2026-09-01 12:00'))->isQuiet());
        self::assertSame([], $events->at($this->at('2026-09-01 12:00'))->events);
    }

    public function testAReferenceTheSeasonAndAnEventBothBringComesOnce(): void
    {
        $season = new Season('winter', SeasonSchedule::once(self::ZONE, '2026-12-01', '2026-12-31'), [Content::quest('quest:hunt'), Content::quest('quest:hunt')], [
            new SeasonalEvent('first', 1, null, [Content::quest('quest:hunt'), Content::quest('quest:sing')]),
        ]);
        $events = new SeasonalEvents(new InMemorySeasonCatalogue([$season]), new MockClock($this->at('2026-12-10 00:00')));

        self::assertSame(['quest:hunt', 'quest:sing'], $this->strings($events->active(ContentKind::Quest)));
        self::assertTrue($events->isActive(ContentKind::Quest, ResourceReference::fromString('quest:sing')));
        self::assertFalse($events->isActive(ContentKind::Item, ResourceReference::fromString('quest:sing')));
        self::assertFalse($events->isActive(ContentKind::Quest, ResourceReference::fromString('quest:sing'), $this->at('2027-01-01 00:00')));
    }

    public function testMultipliersOfEventsThatMeetNeverAddUp(): void
    {
        $season = new Season('winter', SeasonSchedule::once(self::ZONE, '2026-12-01', '2026-12-31'), [], [
            new SeasonalEvent('half', 1, null, [], new Multiplier('points', 150), 1),
            new SeasonalEvent('double', 1, null, [], new Multiplier('points', 200), 1),
            new SeasonalEvent('more', 1, null, [], new Multiplier('points', 180), 1),
            new SeasonalEvent('xp', 1, null, [], new Multiplier('xp', 300), 1),
            new SeasonalEvent('none', 1),
        ]);
        $events = new SeasonalEvents(new InMemorySeasonCatalogue([$season]), new MockClock($this->at('2026-12-10 00:00')));

        self::assertSame(200, $events->multiplier('points')?->percent);
        self::assertSame(300, $events->multiplier('xp')?->percent);
        self::assertNull($events->multiplier('points', $this->at('2027-01-01 00:00')));
    }

    public function testOfTwoEqualMultipliersTheFirstIsKept(): void
    {
        $first = new Multiplier('points', 150);
        $season = new Season('winter', SeasonSchedule::once(self::ZONE, '2026-12-01', '2026-12-31'), [], [
            new SeasonalEvent('one', 1, null, [], $first, 1),
            new SeasonalEvent('two', 1, null, [], new Multiplier('points', 150), 1),
        ]);

        self::assertSame($first, new SeasonalEvents(new InMemorySeasonCatalogue([$season]), new MockClock($this->at('2026-12-10 00:00')))->multiplier('points'));
    }

    public function testTheEventsAnnouncedComeSoonestFirst(): void
    {
        $events = $this->sample('2026-12-15 08:00');
        $announced = $events->announced();

        self::assertSame(['winter-festival:gift-week:2026-12-15'], array_map(static fn (ActiveEvent $active): string => $active->key(), $announced));
        self::assertSame([], $events->announced($this->at('2026-12-22 00:00')));
        self::assertSame(['spooky-season:masquerade:2026-10-15'], array_map(static fn (ActiveEvent $active): string => $active->key(), $events->announced($this->at('2026-10-25 00:00'))));
        self::assertSame([], $events->announced($this->at('2026-12-15 08:00'), 0));
    }

    public function testTheEventsAnnouncedBeforeTheirSeasonStartsAreFound(): void
    {
        $season = new Season('winter', SeasonSchedule::yearly(self::ZONE, '2026-12-15', 10), [], [
            new SeasonalEvent('opening', 1, 2, [], null, 5),
            new SeasonalEvent('second', 2, 2, [], null, 6),
            new SeasonalEvent('quiet', 1),
        ]);
        $events = new SeasonalEvents(new InMemorySeasonCatalogue([$season]), new MockClock($this->at('2026-12-12 00:00')));

        self::assertSame(['winter:opening:2026-12-15', 'winter:second:2026-12-15'], array_map(static fn (ActiveEvent $active): string => $active->key(), $events->announced()));
        self::assertSame(['winter:opening:2026-12-15'], array_map(static fn (ActiveEvent $active): string => $active->key(), $events->announced(limit: 1)));
    }

    public function testTheAnnouncedEventsOfOneDayAreOrderedByTheirKey(): void
    {
        $schedule = SeasonSchedule::once(self::ZONE, '2026-12-15', '2026-12-20');
        $catalogue = new InMemorySeasonCatalogue([
            new Season('b-season', $schedule, [], [new SeasonalEvent('event', 1, null, [], null, 3)]),
            new Season('a-season', $schedule, [], [new SeasonalEvent('event', 1, null, [], null, 3)]),
        ]);

        self::assertSame(
            ['a-season:event:2026-12-15', 'b-season:event:2026-12-15'],
            array_map(static fn (ActiveEvent $active): string => $active->key(), new SeasonalEvents($catalogue, new MockClock($this->at('2026-12-13 00:00')))->announced()),
        );
    }

    public function testNoMoreEventsAreAnnouncedThanTheCeiling(): void
    {
        $schedule = SeasonSchedule::once(self::ZONE, '2026-12-15', '2026-12-20');
        $seasons = array_map(static fn (int $index): Season => new Season('s'.$index, $schedule, [], [new SeasonalEvent('a', 1, null, [], null, 3), new SeasonalEvent('b', 1, null, [], null, 3)]), range(1, 30));
        $events = new SeasonalEvents(new InMemorySeasonCatalogue($seasons), new MockClock($this->at('2026-12-13 00:00')));

        self::assertCount(SeasonalEvents::MAX_UPCOMING, $events->announced(limit: 1000));
        self::assertCount(SeasonalEvents::MAX_UPCOMING, $events->announced(limit: SeasonalEvents::MAX_UPCOMING));
    }

    public function testTheEventsOfEverySeasonThatIsOnAreGathered(): void
    {
        $schedule = SeasonSchedule::once(self::ZONE, '2026-12-01', '2026-12-31');
        $catalogue = new InMemorySeasonCatalogue([
            new Season('first', $schedule, [], [new SeasonalEvent('a'), new SeasonalEvent('b')]),
            new Season('second', $schedule, [], [new SeasonalEvent('c')]),
        ]);
        $snapshot = new SeasonalEvents($catalogue, new MockClock($this->at('2026-12-10 00:00')))->at();

        self::assertSame(['first:a:2026-12-01', 'first:b:2026-12-01', 'second:c:2026-12-01'], array_map(static fn (ActiveEvent $active): string => $active->key(), $snapshot->events));
        self::assertCount(2, $snapshot->seasons);
    }

    public function testTenEventsAreAnnouncedByDefaultAndNeverANegativeNumber(): void
    {
        $schedule = SeasonSchedule::once(self::ZONE, '2026-12-15', '2026-12-20');
        $seasons = array_map(static fn (int $index): Season => new Season(\sprintf('s%02d', $index), $schedule, [], [new SeasonalEvent('a', 1, null, [], null, 3)]), range(1, 12));
        $events = new SeasonalEvents(new InMemorySeasonCatalogue($seasons), new MockClock($this->at('2026-12-13 00:00')));

        self::assertCount(10, $events->announced());
        self::assertSame([], $events->announced(limit: -1));
        self::assertCount(1, $events->announced(limit: 1));
    }

    public function testTheFirstReferenceOfAContentIsTheOneKept(): void
    {
        $first = ResourceReference::fromString('quest:hunt');
        $second = ResourceReference::fromString('quest:hunt');
        $snapshot = new Snapshot($this->at('2026-12-10 00:00'), [], []);
        self::assertSame([], $snapshot->contents(ContentKind::Quest));

        $schedule = SeasonSchedule::once(self::ZONE, '2026-12-01', '2026-12-31');
        $season = new Season('winter', $schedule, [new Content(ContentKind::Quest, $first), new Content(ContentKind::Quest, $second)]);
        $period = $season->periodAt($this->at('2026-12-10 00:00'));
        self::assertNotNull($period);
        $snapshot = new Snapshot($this->at('2026-12-10 00:00'), [new ActiveSeason($season, $period)], []);

        self::assertSame([$first], $snapshot->contents(ContentKind::Quest));
    }

    public function testTheNextTimeOfASeasonIsFound(): void
    {
        $events = $this->sample('2027-01-10 00:00');

        self::assertSame('2027-12-15', $events->next('winter-festival')?->code);
        self::assertSame('2027-03-20', $events->next('spring-cleaning-2027')?->code);
        self::assertNull($events->next('spring-cleaning-2027', $this->at('2027-03-21 00:00')));
        self::assertNull($events->next('summer'));
    }

    public function testTheCatalogueKeepsItsOrderAndRefusesTwoSeasonsOfOneName(): void
    {
        $schedule = SeasonSchedule::once(self::ZONE, '2026-12-15', '2026-12-20');
        $catalogue = new InMemorySeasonCatalogue([new Season('b', $schedule), new Season('a', $schedule)]);

        self::assertSame(['b', 'a'], array_map(static fn (Season $season): string => $season->id, $catalogue->all()));
        self::assertSame('a', $catalogue->find('a')?->id);
        self::assertNull($catalogue->find('c'));
        self::assertSame([], new InMemorySeasonCatalogue()->all());

        $this->expectException(InvalidSeason::class);
        $this->expectExceptionMessageIsOrContains('Two seasons are named "a".');

        new InMemorySeasonCatalogue([new Season('a', $schedule), new Season('a', $schedule)]);
    }

    private function sample(string $now): SeasonalEvents
    {
        /** @var list<array<string, mixed>> $definitions */
        $definitions = require \dirname(__DIR__, 2).'/resources/sample-seasons.php';

        return new SeasonalEvents(new InMemorySeasonCatalogue(SeasonReader::all($definitions)), new MockClock($this->at($now)));
    }

    /**
     * @param list<ResourceReference> $references
     *
     * @return list<string>
     */
    private function strings(array $references): array
    {
        return array_map(static fn (ResourceReference $reference): string => $reference->toString(), $references);
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone(self::ZONE));
    }
}
