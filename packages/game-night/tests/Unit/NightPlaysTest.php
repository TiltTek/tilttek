<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Tests\Unit;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\Details;
use Tilttek\GameCatalog\Exception\EntryNotFound;
use Tilttek\GameCatalog\Store\InMemoryCatalogStore;
use Tilttek\GameNight\Exception\NightNotFound;
use Tilttek\GameNight\Exception\NightRefused;
use Tilttek\GameNight\GameNights;
use Tilttek\GameNight\NightPlays;
use Tilttek\GameNight\Store\InMemoryNightStore;
use Tilttek\GameNight\Table;
use Tilttek\GameNight\Tests\Unit\Fixture\Journal;

#[CoversClass(NightPlays::class)]
final class NightPlaysTest extends TestCase
{
    private MockClock $clock;

    private GameNights $nights;

    private Journal $journal;

    private NightPlays $plays;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2027-03-01 10:00 UTC'));
        $catalog = new Catalog(new InMemoryCatalogStore(), $this->clock);
        $catalog->add('g-1', new Details('Tide'));

        $store = new InMemoryNightStore();
        $this->nights = new GameNights($store, $this->clock, $catalog);
        $this->journal = new Journal();
        $this->plays = new NightPlays($store, $this->clock, $catalog, $this->journal);

        $this->nights->plan('n-1', new DateTimeImmutable('2027-03-05 19:00 UTC'), new DateTimeImmutable('2027-03-05 23:00 UTC'), 'venue-1', 'host-1', [new Table('t-1', 4)]);
        $this->nights->publish('n-1');
        $this->nights->register('n-1', 'm-1', true);
        $this->nights->register('n-1', 'm-2');
        $this->nights->register('n-1', 'm-3', true);
        $this->nights->register('n-1', 'm-4');

        $this->clock->modify('2027-03-05 19:00 UTC');
    }

    public function testAGamePlayedGoesToThePlayLog(): void
    {
        $play = $this->plays->record('n-1', 't-1', 'g-1', ['m-1', 'm-2'], 1, 50);

        self::assertSame([$play], $this->journal->plays);
        self::assertSame(['m-1', 'm-2'], $play->players);
        self::assertSame(1, $play->guests);
        self::assertSame(50, $play->minutes);
        self::assertSame('2027-03-05 19:00', $play->playedAt->format('Y-m-d H:i'));
        $alone = $this->plays->record('n-1', 't-1', 'g-1', ['m-2']);

        self::assertNull($alone->minutes);
        self::assertSame(0, $alone->guests);
        self::assertSame([$play, $alone], $this->journal->plays);
    }

    public function testAGameIsNotRecordedBeforeTheNightStarts(): void
    {
        $this->clock->modify('2027-03-05 18:59 UTC');

        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The games of a night are recorded once it has started, unless it is cancelled.');

        $this->plays->record('n-1', 't-1', 'g-1', ['m-1']);
    }

    public function testAGameOfACancelledNightIsNotRecorded(): void
    {
        $this->nights->cancel('n-1');

        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The games of a night are recorded once it has started, unless it is cancelled.');

        $this->plays->record('n-1', 't-1', 'g-1', ['m-1']);
    }

    public function testAGameIsPlayedAtATableOfTheNight(): void
    {
        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The table t-9 is not one of the night.');

        $this->plays->record('n-1', 't-9', 'g-1', ['m-1']);
    }

    public function testAPlayerHoldsAPlace(): void
    {
        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The member m-4 holds no place at the night.');

        $this->plays->record('n-1', 't-1', 'g-1', ['m-1', 'm-4']);
    }

    public function testAStrangerIsNotAPlayer(): void
    {
        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The member m-9 holds no place at the night.');

        $this->plays->record('n-1', 't-1', 'g-1', ['m-9']);
    }

    public function testTheGuestsAreThoseThePlayersBrought(): void
    {
        self::assertSame(1, $this->plays->record('n-1', 't-1', 'g-1', ['m-1'], 1)->guests);

        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The guests who played are guests the players brought.');

        $this->plays->record('n-1', 't-1', 'g-1', ['m-1', 'm-2'], 2);
    }

    public function testTheGameIsInTheCatalogue(): void
    {
        try {
            $this->plays->record('n-1', 't-1', 'g-9', ['m-1']);
            self::fail('A game out of the catalogue was recorded.');
        } catch (EntryNotFound) {
            self::assertSame([], $this->journal->plays);
        }
    }

    public function testAnUnknownNightIsNotFound(): void
    {
        $this->expectException(NightNotFound::class);

        $this->plays->record('n-9', 't-1', 'g-1', ['m-1']);
    }
}
