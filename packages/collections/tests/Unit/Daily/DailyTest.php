<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Daily;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Activity\Filter;
use Tilttek\Collections\Copy;
use Tilttek\Collections\CopyMove;
use Tilttek\Collections\Daily\Daily;
use Tilttek\Collections\Daily\DailyCalendar;
use Tilttek\Collections\Daily\DailyPick;
use Tilttek\Collections\Daily\DailyProjection;
use Tilttek\Collections\Daily\FixedDate;
use Tilttek\Collections\Daily\InMemoryDailyStore;
use Tilttek\Collections\Daily\PickOrigin;
use Tilttek\Collections\Exception\InvalidCollection;
use Tilttek\Collections\Exception\NotFound;
use Tilttek\Collections\FixedCatalog;
use Tilttek\Collections\Source;
use Tilttek\Collections\Tests\Unit\Fixture\Kit;

#[CoversClass(DailyCalendar::class)]
#[CoversClass(Daily::class)]
#[CoversClass(DailyProjection::class)]
final class DailyTest extends TestCase
{
    public function testADayFallsInTheCalendarsTimezone(): void
    {
        $kit = Kit::standard();

        self::assertSame('2026-11-06', $kit->calendar->dayOf(new DateTimeImmutable('2026-11-07 03:00 UTC')));
        self::assertSame('2026-11-07', $kit->calendar->dayOf(new DateTimeImmutable('2026-11-07 06:00 UTC')));
        self::assertSame('America/Toronto', $kit->calendar->timezone->getName());
    }

    public function testAFreeDayDrawsByRarityFromTheCalendarsSets(): void
    {
        $kit = Kit::standard();
        $pick = $kit->calendar->pick('2026-11-07');

        self::assertSame(PickOrigin::Drawn, $pick->origin);
        self::assertSame('2026-11-07', $pick->day);
        self::assertSame($this->drawn('salt', '2026-11-07'), $pick->item);
        self::assertEquals($pick, $kit->calendar->compute('2026-11-07'));

        $counts = ['deckhand' => 0, 'pirate' => 0, 'captain' => 0];
        $day = new DateTimeImmutable('2027-01-01');

        for ($i = 0; $i < 730; ++$i) {
            ++$counts[$kit->calendar->compute($day->modify(\sprintf('+%d days', $i))->format('Y-m-d'))->item];
        }

        self::assertEqualsWithDelta(511, $counts['deckhand'], 60);
        self::assertEqualsWithDelta(182, $counts['pirate'], 50);
        self::assertGreaterThan(10, $counts['captain']);
    }

    public function testRulesComeBeforeTheDrawInTheirOrder(): void
    {
        $kit = Kit::standard([new FixedDate('10-31', 'captain'), new FixedDate('2026-10-31', 'pirate'), new FixedDate('12-25', 'medal')]);

        self::assertEquals(new DailyPick('2026-10-31', 'captain', PickOrigin::Rule), $kit->calendar->pick('2026-10-31'));
        self::assertSame('medal', $kit->calendar->pick('2026-12-25')->item);
        self::assertSame(PickOrigin::Drawn, $kit->calendar->pick('2026-10-30')->origin);

        $kit = Kit::standard([new FixedDate('10-31', 'parrot')]);
        $this->expectExceptionObject(NotFound::of('item', 'parrot'));
        $kit->calendar->pick('2026-10-31');
    }

    public function testACalendarIsGeneratedAheadKeepingWhatIsKept(): void
    {
        $kit = Kit::standard([new FixedDate('11-09', 'captain')]);
        $kit->calendar->choose('2026-11-08', 'medal');
        $kit->picks->save(new DailyPick('2026-11-10', 'pirate', PickOrigin::Drawn));

        $picks = $kit->calendar->generate('2026-11-07', '2026-11-11');
        self::assertSame(['2026-11-07', '2026-11-08', '2026-11-09', '2026-11-10', '2026-11-11'], array_map(static fn (DailyPick $pick): string => $pick->day, $picks));
        self::assertSame([PickOrigin::Drawn, PickOrigin::Chosen, PickOrigin::Rule, PickOrigin::Drawn, PickOrigin::Drawn], array_map(static fn (DailyPick $pick): PickOrigin => $pick->origin, $picks));
        self::assertSame(['medal', 'captain', 'pirate'], [$picks[1]->item, $picks[2]->item, $picks[3]->item]);
        self::assertEquals($picks, $kit->picks->between('2026-11-07', '2026-11-11'));

        $again = $kit->calendar->generate('2026-11-07', '2026-11-11', true);
        self::assertSame('medal', $again[1]->item);
        self::assertSame($this->drawn('salt', '2026-11-10'), $again[3]->item);
        self::assertSame($this->drawn('salt', '2026-11-10'), $kit->calendar->pick('2026-11-10')->item);
        self::assertSame([], $kit->calendar->generate('2026-11-12', '2026-11-11'));
        self::assertCount(1, $kit->calendar->generate('2026-11-12', '2026-11-12'));
    }

    public function testAKeptPickWinsUntilReleased(): void
    {
        $kit = Kit::standard();
        $kit->calendar->choose('2026-11-07', 'medal');

        self::assertEquals(new DailyPick('2026-11-07', 'medal', PickOrigin::Chosen), $kit->calendar->pick('2026-11-07'));
        self::assertTrue($kit->calendar->release('2026-11-07'));
        self::assertFalse($kit->calendar->release('2026-11-07'));
        self::assertSame(PickOrigin::Drawn, $kit->calendar->pick('2026-11-07')->origin);

        $this->expectExceptionObject(NotFound::of('item', 'parrot'));
        $kit->calendar->choose('2026-11-07', 'parrot');
    }

    public function testACalendarIsGeneratedTenYearsAtATimeAtMost(): void
    {
        $kit = Kit::standard();
        $catalog = FixedCatalog::fromArray(['rarities' => ['common' => 1], 'items' => ['a' => ['name' => 'A', 'rarity' => 'common']]]);
        $calendar = new DailyCalendar($catalog, new InMemoryDailyStore(), new DateTimeZone('UTC'), 'salt');

        self::assertCount(3660, $calendar->generate('2026-01-01', new DateTimeImmutable('2026-01-01')->modify('+3659 days')->format('Y-m-d')));

        try {
            $calendar->generate('2026-01-01', new DateTimeImmutable('2026-01-01')->modify('+3660 days')->format('Y-m-d'));
            self::fail('Generated.');
        } catch (InvalidCollection $invalidCollection) {
            self::assertSame('A calendar is generated 3660 days at a time at most.', $invalidCollection->getMessage());
        }

        $this->expectExceptionObject(InvalidCollection::because('"2026-02-30" is not a day written Y-m-d.'));
        $kit->calendar->generate('2026-02-30', '2026-03-01');
    }

    public function testAMemberGetsTheItemOfTheDayOncePerDay(): void
    {
        $kit = Kit::standard([new FixedDate('11-07', 'captain'), new FixedDate('11-08', 'pirate')]);
        $member = Kit::ref('member:1');

        self::assertSame('captain', $kit->daily->today()->item);
        self::assertSame('pirate', $kit->daily->today(new DateTimeImmutable('2026-11-08 12:00 UTC'))->item);

        $copy = $kit->daily->claim($member);
        self::assertSame(['captain', Source::Daily, '2026-11-07 12:00'], [$copy->item, $copy->source, $copy->acquiredAt->format('Y-m-d H:i')]);
        self::assertSame('#daily/2026-11-07/'.hash('sha256', 'member:1'), $copy->key);
        self::assertEquals($copy, $kit->daily->claim($member, new DateTimeImmutable('2026-11-07 20:00 UTC')));
        self::assertSame('pirate', $kit->daily->claim($member, new DateTimeImmutable('2026-11-08 06:00 UTC'))->item);
        self::assertSame('captain', $kit->daily->claim(Kit::ref('member:2'), new DateTimeImmutable('2026-11-07 18:00 UTC'))->item);
        self::assertCount(2, $kit->collections->copiesOf($member));

        self::assertTrue($kit->daily->takeBack($member, new DateTimeImmutable('2026-11-08 07:00 UTC')));
        self::assertFalse($kit->daily->takeBack($member, new DateTimeImmutable('2026-11-08 07:00 UTC')));
        self::assertSame(1, $kit->daily->forget(new DateTimeImmutable('2026-11-07 18:00 UTC')));
        self::assertSame(1, $kit->daily->forget(null));
    }

    public function testTheProjectionGivesTheItemOfTheDayOnTheActivity(): void
    {
        $kit = Kit::standard([new FixedDate('11-07', 'captain'), new FixedDate('11-08', 'pirate'), new FixedDate('11-09', 'deckhand')]);

        self::assertSame('collections.daily', $kit->projection->name());
        self::assertEquals(Filter::all()->verbs('page.viewed', 'member.signed_in'), $kit->projection->filter());
        self::assertSame('daily', new DailyProjection($kit->daily, ['page.viewed'], 'daily')->name());

        $kit->record('v1', 'member:1', 'page.viewed', '2026-11-07 14:00 UTC');
        $kit->record('v2', 'member:1', 'page.viewed', '2026-11-07 15:00 UTC');
        $kit->record('v3', 'member:1', 'member.signed_in', '2026-11-08 14:00 UTC');
        $kit->record('x1', 'member:1', 'game.played', '2026-11-09 14:00 UTC');
        self::assertSame(['captain', 'pirate'], array_map(static fn (Copy $copy): string => $copy->item, $kit->collections->copiesOf(Kit::ref('member:1'))));
        self::assertSame(['granted captain to member:1', 'granted pirate to member:1'], $kit->recorder->events());

        $kit->cancel('v3', '2026-11-08 16:00 UTC');
        self::assertSame(['captain'], array_map(static fn (Copy $copy): string => $copy->item, $kit->collections->copiesOf(Kit::ref('member:1'))));
    }

    public function testARebuildGivesAgainWhatWasNotTraded(): void
    {
        $kit = Kit::standard([new FixedDate('11-07', 'captain'), new FixedDate('11-08', 'pirate')]);
        $kit->record('v1', 'member:1', 'page.viewed', '2026-11-07 14:00 UTC');
        $kit->record('v2', 'member:1', 'page.viewed', '2026-11-08 14:00 UTC');

        $traded = $kit->collections->copiesOf(Kit::ref('member:1'))[0];
        $kit->collections->move([new CopyMove($traded->id, Kit::ref('member:1'), Kit::ref('member:2'))]);
        $kit->calendar->choose('2026-11-08', 'medal');

        $kit->projector->rebuild($kit->projection);
        self::assertSame(['medal'], array_map(static fn (Copy $copy): string => $copy->item, $kit->collections->copiesOf(Kit::ref('member:1'))));
        self::assertSame(['captain'], array_map(static fn (Copy $copy): string => $copy->item, $kit->collections->copiesOf(Kit::ref('member:2'))));

        $kit->calendar->release('2026-11-08');
        $kit->projector->rebuild($kit->projection, new DateTimeImmutable('2026-11-08 00:00 UTC'));
        self::assertSame(['pirate'], array_map(static fn (Copy $copy): string => $copy->item, $kit->collections->copiesOf(Kit::ref('member:1'))));
    }

    public function testTheItemOfTheDayIsClaimedOnSomeVerb(): void
    {
        $this->expectExceptionObject(InvalidCollection::because('The item of the day is claimed on at least one verb.'));

        new DailyProjection(Kit::standard()->daily, []);
    }

    public function testACalendarWithoutSetsDrawsFromEveryItem(): void
    {
        $catalog = FixedCatalog::fromArray(Kit::definition());
        $calendar = new DailyCalendar($catalog, new InMemoryDailyStore(), new DateTimeZone('UTC'), 'pepper');
        $seen = [];

        for ($i = 1; $i <= 200; ++$i) {
            $seen[$calendar->compute(\sprintf('2026-%02d-%02d', intdiv($i - 1, 28) + 1, ($i - 1) % 28 + 1))->item] = true;
        }

        self::assertContains('ember-d6', array_keys($seen));
        self::assertSame($this->drawn('pepper', '2026-03-03', []), $calendar->compute('2026-03-03')->item);
    }

    /**
     * @param list<string> $sets
     */
    private function drawn(string $salt, string $day, array $sets = ['crew']): string
    {
        $randomizer = new Randomizer(new Xoshiro256StarStar(hash('sha256', $salt."\0".$day, true)));

        return FixedCatalog::fromArray(Kit::definition())->catalog()->odds($sets)->draw($randomizer)->code;
    }
}
