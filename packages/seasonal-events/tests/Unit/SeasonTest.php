<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\SeasonalEvents\ActiveEvent;
use Tilttek\SeasonalEvents\ActiveSeason;
use Tilttek\SeasonalEvents\Content;
use Tilttek\SeasonalEvents\ContentKind;
use Tilttek\SeasonalEvents\Exception\InvalidSeason;
use Tilttek\SeasonalEvents\Multiplier;
use Tilttek\SeasonalEvents\Period;
use Tilttek\SeasonalEvents\Season;
use Tilttek\SeasonalEvents\SeasonalEvent;
use Tilttek\SeasonalEvents\SeasonSchedule;

#[CoversClass(Season::class)]
#[CoversClass(SeasonalEvent::class)]
#[CoversClass(ActiveEvent::class)]
#[CoversClass(ActiveSeason::class)]
#[CoversClass(Multiplier::class)]
#[CoversClass(Content::class)]
#[CoversClass(ContentKind::class)]
final class SeasonTest extends TestCase
{
    private const string ZONE = 'America/Toronto';

    public function testAnEventRunsFromItsDayForItsDaysInsideOneTimeOfTheSeason(): void
    {
        $event = new SeasonalEvent('gift-week', 8, 7, [Content::item('item:wrapped-die')], new Multiplier('points', 200), 7);
        $season = new Season('winter', SeasonSchedule::yearly(self::ZONE, '2026-12-15', 23), [Content::decoration('decoration:snow')], [$event]);
        $period = $season->periodAt($this->at('2026-12-20 10:00'));
        self::assertInstanceOf(Period::class, $period);

        $active = $season->eventIn($event, $period);

        self::assertSame('2026-12-22T00:00:00-05:00', $active->from->format(\DATE_ATOM));
        self::assertSame('2026-12-29T00:00:00-05:00', $active->until->format(\DATE_ATOM));
        self::assertFalse($active->contains($this->at('2026-12-21 23:59:59')));
        self::assertTrue($active->contains($this->at('2026-12-22 00:00')));
        self::assertTrue($active->contains($this->at('2026-12-28 23:59:59')));
        self::assertFalse($active->contains($this->at('2026-12-29 00:00')));
        self::assertSame('winter:gift-week:2026-12-15', $active->key());
        self::assertSame('2026-12-15T00:00:00-05:00', $active->announcedFrom()->format(\DATE_ATOM));
        self::assertFalse($active->isAnnouncedAt($this->at('2026-12-14 23:59:59')));
        self::assertTrue($active->isAnnouncedAt($this->at('2026-12-15 00:00')));
        self::assertTrue($active->isAnnouncedAt($this->at('2026-12-21 23:59:59')));
        self::assertFalse($active->isAnnouncedAt($this->at('2026-12-22 00:00')));
        self::assertSame($event, $season->event('gift-week'));
        self::assertNull($season->event('summer'));
        self::assertSame([
            'season' => 'winter',
            'event' => 'gift-week',
            'period' => '2026-12-15',
            'from' => '2026-12-22T00:00:00-05:00',
            'until' => '2026-12-29T00:00:00-05:00',
            'contents' => [['kind' => 'item', 'reference' => 'item:wrapped-die']],
            'multiplier' => ['ledger' => 'points', 'percent' => 200],
        ], $active->toArray());
        self::assertSame([
            'season' => 'winter',
            'period' => ['code' => '2026-12-15', 'from' => '2026-12-15T00:00:00-05:00', 'until' => '2027-01-07T00:00:00-05:00'],
            'contents' => [['kind' => 'decoration', 'reference' => 'decoration:snow']],
        ], new ActiveSeason($season, $period)->toArray());
    }

    public function testAnEventNeverRunsPastTheEndOfItsSeason(): void
    {
        $tail = new SeasonalEvent('tail', 3, 10);
        $rest = new SeasonalEvent('rest', 2);
        $season = new Season('short', SeasonSchedule::once(self::ZONE, '2026-12-01', '2026-12-04'), [], [$tail, $rest]);
        $period = $season->periodAt($this->at('2026-12-01 00:00'));
        self::assertInstanceOf(Period::class, $period);

        self::assertSame('2026-12-05T00:00:00-05:00', $season->eventIn($tail, $period)->until->format(\DATE_ATOM));
        self::assertSame('2026-12-02T00:00:00-05:00', $season->eventIn($rest, $period)->from->format(\DATE_ATOM));
        self::assertSame('2026-12-05T00:00:00-05:00', $season->eventIn($rest, $period)->until->format(\DATE_ATOM));
        self::assertNull(new SeasonalEvent('rest', 2)->multiplier);
        self::assertSame(0, $rest->announceDays);
        self::assertSame([], $rest->contents);
    }

    public function testAnEventOnTheLastDayOfItsSeasonIsAccepted(): void
    {
        $season = new Season('short', SeasonSchedule::once(self::ZONE, '2026-12-01', '2026-12-04'), [], [new SeasonalEvent('last', 4)]);

        self::assertCount(1, $season->events);
    }

    public function testAMultiplierRoundsAGainDown(): void
    {
        $multiplier = new Multiplier('points', 150);

        self::assertSame(15, $multiplier->apply(10));
        self::assertSame(1, $multiplier->apply(1));
        self::assertSame(0, $multiplier->apply(0));
        self::assertSame(148, $multiplier->apply(99));
        self::assertSame(1_500, $multiplier->apply(1_000));
        self::assertSame(['ledger' => 'points', 'percent' => 150], $multiplier->toArray());
        self::assertSame(101, new Multiplier('xp', 101)->percent);
        self::assertSame(500, new Multiplier('xp', 500)->percent);
    }

    public function testAnEventStartsOnTheFirstDayByDefaultAndTakesTheEdgesOfItsBounds(): void
    {
        $event = new SeasonalEvent('plain');

        self::assertSame(1, $event->fromDay);
        self::assertNull($event->days);
        self::assertSame(0, $event->announceDays);
        self::assertSame(1, new SeasonalEvent('one-day', 1, 1)->days);
        self::assertSame(1, new SeasonalEvent('announced', 1, null, [], new Multiplier('points', 150), 1)->announceDays);
    }

    public function testAnEventWithoutAMultiplierIsWrittenWithoutOne(): void
    {
        $season = new Season('winter', SeasonSchedule::once(self::ZONE, '2026-12-01', '2026-12-31'), [], [new SeasonalEvent('plain')]);
        $period = $season->periodAt($this->at('2026-12-10 00:00'));
        self::assertInstanceOf(Period::class, $period);

        self::assertNull($season->eventIn($season->events[0], $period)->toArray()['multiplier']);
    }

    public function testAContentIsAReferenceOfAKind(): void
    {
        self::assertSame(['kind' => 'quest', 'reference' => 'quest:hunt'], Content::quest('quest:hunt')->toArray());
        self::assertSame(ContentKind::Item, Content::item('item:hat')->kind);
        self::assertSame('decoration:snow', Content::decoration('decoration:snow')->reference->toString());
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refused(): iterable
    {
        $schedule = SeasonSchedule::once(self::ZONE, '2026-12-01', '2026-12-04');

        yield 'a season identifier' => [static fn (): Season => new Season('Winter', $schedule), 'A season identifier is 1 to 64'];
        yield 'too many contents' => [static fn (): Season => new Season('winter', $schedule, array_fill(0, 201, Content::quest('quest:a'))), 'A season brings at most 200 contents and holds at most 50 events.'];
        yield 'too many events' => [static fn (): Season => new Season('winter', $schedule, [], array_map(static fn (int $index): SeasonalEvent => new SeasonalEvent('e'.$index), range(1, 51))), 'A season brings at most 200 contents and holds at most 50 events.'];
        yield 'two events of one name' => [static fn (): Season => new Season('winter', $schedule, [], [new SeasonalEvent('a'), new SeasonalEvent('a')]), 'The season "winter" holds two events "a".'];
        yield 'an event after the season' => [static fn (): Season => new Season('winter', $schedule, [], [new SeasonalEvent('late', 5)]), 'The season "winter" lasts 4 days; its event "late" starts on day 5.'];
        yield 'an event identifier' => [static fn (): SeasonalEvent => new SeasonalEvent('-a'), 'An event identifier is 1 to 64'];
        yield 'an event before day 1' => [static fn (): SeasonalEvent => new SeasonalEvent('a', 0), 'An event starts on day 1 to 183 of its season; 0 is not one.'];
        yield 'an event after day 183' => [static fn (): SeasonalEvent => new SeasonalEvent('a', 184), 'An event starts on day 1 to 183 of its season; 184 is not one.'];
        yield 'an event of no day' => [static fn (): SeasonalEvent => new SeasonalEvent('a', 1, 0), 'An event lasts 1 to 183 days; 0 is not one.'];
        yield 'an event too long' => [static fn (): SeasonalEvent => new SeasonalEvent('a', 1, 184), 'An event lasts 1 to 183 days; 184 is not one.'];
        yield 'an event bringing too much' => [static fn (): SeasonalEvent => new SeasonalEvent('a', 1, null, array_fill(0, 101, Content::quest('quest:a'))), 'An event brings at most 100 contents.'];
        yield 'an announcement before the past' => [static fn (): SeasonalEvent => new SeasonalEvent('a', 1, null, [], null, -1), 'An event is announced 0 to 90 days ahead; -1 is not.'];
        yield 'an announcement too early' => [static fn (): SeasonalEvent => new SeasonalEvent('a', 1, null, [], null, 91), 'An event is announced 0 to 90 days ahead; 91 is not.'];
        yield 'a multiplier by surprise' => [static fn (): SeasonalEvent => new SeasonalEvent('double', 1, null, [], new Multiplier('points', 200)), 'The event "double" multiplies gains: it is announced at least one day before it starts.'];
        yield 'a ledger' => [static fn (): Multiplier => new Multiplier('Points', 200), 'A ledger is 1 to 64'];
        yield 'a multiplier that adds nothing' => [static fn (): Multiplier => new Multiplier('points', 100), 'A multiplier is 101 to 500 percent; 100 is not.'];
        yield 'a multiplier too high' => [static fn (): Multiplier => new Multiplier('points', 501), 'A multiplier is 101 to 500 percent; 501 is not.'];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('refused')]
    public function testWhatDoesNotHoldTogetherIsRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidSeason::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testTheBoundsOfASeasonAreAccepted(): void
    {
        $schedule = SeasonSchedule::once(self::ZONE, '2026-01-01', '2026-07-02');
        $season = new Season(
            str_repeat('a', 64),
            $schedule,
            array_fill(0, 200, Content::quest('quest:a')),
            array_map(static fn (int $index): SeasonalEvent => new SeasonalEvent('e'.$index, 183, 183, array_fill(0, 100, Content::quest('quest:a')), new Multiplier('points', 200), 90), range(1, 50)),
        );

        self::assertCount(50, $season->events);
        self::assertSame('0', new Season('0', $schedule)->id);
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone(self::ZONE));
    }
}
