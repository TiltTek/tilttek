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
use Tilttek\GameNight\Event\NightCancelled;
use Tilttek\GameNight\Event\NightFull;
use Tilttek\GameNight\Event\NightPublished;
use Tilttek\GameNight\Event\SeatOffered;
use Tilttek\GameNight\Exception\InvalidNight;
use Tilttek\GameNight\Exception\NightConflict;
use Tilttek\GameNight\Exception\NightNotFound;
use Tilttek\GameNight\Exception\NightRefused;
use Tilttek\GameNight\GameNights;
use Tilttek\GameNight\NightStatus;
use Tilttek\GameNight\RegistrationStatus;
use Tilttek\GameNight\Store\InMemoryNightStore;
use Tilttek\GameNight\Table;
use Tilttek\GameNight\Tests\Unit\Fixture\Observed;
use Tilttek\GameNight\Vote\VoteOutcome;

#[CoversClass(GameNights::class)]
#[CoversClass(NightPublished::class)]
#[CoversClass(NightFull::class)]
#[CoversClass(NightCancelled::class)]
#[CoversClass(SeatOffered::class)]
final class GameNightsTest extends TestCase
{
    private MockClock $clock;

    private Observed $observed;

    private Catalog $catalog;

    private GameNights $nights;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2027-03-01 10:00 UTC'));
        $this->observed = new Observed();
        $this->catalog = new Catalog(new InMemoryCatalogStore(), $this->clock);
        $this->catalog->add('g-1', new Details('Tide'));
        $this->catalog->add('g-2', new Details('Harbour'));
        $this->catalog->hide($this->catalog->add('g-hidden', new Details('Gone'))->id);

        $this->nights = new GameNights(new InMemoryNightStore(), $this->clock, $this->catalog, $this->observed);
    }

    private function published(int $seats = 2): void
    {
        $this->nights->plan('n-1', new DateTimeImmutable('2027-03-05 19:00 UTC'), new DateTimeImmutable('2027-03-05 23:00 UTC'), 'venue-1', 'host-1', [new Table('t-1', $seats)]);
        $this->nights->publish('n-1');

        $this->observed->take();
    }

    public function testANightIsPlannedThenPublished(): void
    {
        $night = $this->nights->plan('n-1', new DateTimeImmutable('2027-03-05 19:00 UTC'), new DateTimeImmutable('2027-03-05 23:00 UTC'), 'venue-1', 'host-1');

        self::assertSame(NightStatus::Draft, $night->status);
        self::assertSame(1, $night->version);
        self::assertSame([], $this->observed->take());

        $this->nights->addTable('n-1', new Table('t-1', 4));
        $published = $this->nights->publish('n-1');

        self::assertSame(NightStatus::Published, $published->status);
        self::assertEquals([new NightPublished('n-1')], $this->observed->take());
        self::assertSame(3, $this->nights->get('n-1')->version);
    }

    public function testANightIsNotPlannedInThePast(): void
    {
        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The night has started.');

        $this->nights->plan('n-1', new DateTimeImmutable('2027-03-01 10:00 UTC'), new DateTimeImmutable('2027-03-01 12:00 UTC'), 'venue-1', 'host-1');
    }

    public function testANightIsPlannedOnce(): void
    {
        $this->published();

        $this->expectException(NightConflict::class);

        $this->nights->plan('n-1', new DateTimeImmutable('2027-03-06 19:00 UTC'), new DateTimeImmutable('2027-03-06 23:00 UTC'), 'venue-1', 'host-1');
    }

    public function testAStartedNightIsNotPublished(): void
    {
        $this->nights->plan('n-1', new DateTimeImmutable('2027-03-05 19:00 UTC'), new DateTimeImmutable('2027-03-05 23:00 UTC'), 'venue-1', 'host-1', [new Table('t-1', 2)]);
        $this->clock->modify('2027-03-05 19:00 UTC');

        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The night has started.');

        $this->nights->publish('n-1');
    }

    public function testAnUnknownNightIsNotFound(): void
    {
        $this->expectException(NightNotFound::class);
        $this->expectExceptionMessageIs('No game night has the identifier n-9.');

        $this->nights->register('n-9', 'm-1');
    }

    public function testTheLastSeatTakenTellsTheNightIsFullOnce(): void
    {
        $this->published();

        self::assertSame(RegistrationStatus::Confirmed, $this->nights->register('n-1', 'm-1')->status);
        self::assertSame([], $this->observed->take());

        $this->nights->register('n-1', 'm-2');
        self::assertEquals([new NightFull('n-1')], $this->observed->take());

        $waiting = $this->nights->register('n-1', 'm-3', true);
        self::assertSame(RegistrationStatus::Waitlisted, $waiting->status);
        self::assertTrue($waiting->guest);
        self::assertSame([], $this->observed->take());
    }

    public function testAWithdrawalOffersTheSeatToTheFirstWaitingOnce(): void
    {
        $this->published();
        $this->nights->register('n-1', 'm-1');
        $this->nights->register('n-1', 'm-2');
        $this->nights->register('n-1', 'm-3');

        $this->observed->take();

        $night = $this->nights->withdraw('n-1', 'm-1');

        self::assertSame(RegistrationStatus::Confirmed, $night->registration('m-3')?->status);
        self::assertEquals([new SeatOffered('n-1', 'm-3')], $this->observed->take());
    }

    public function testAWithdrawalWithNobodyWaitingFreesTheSeat(): void
    {
        $this->published();
        $this->nights->register('n-1', 'm-1');
        $this->nights->register('n-1', 'm-2');
        $this->nights->withdraw('n-1', 'm-2');

        $this->observed->take();

        $this->nights->register('n-1', 'm-3');

        self::assertEquals([new NightFull('n-1')], $this->observed->take());
    }

    public function testATableAddedOffersItsSeats(): void
    {
        $this->published(1);
        $this->nights->register('n-1', 'm-1');
        $this->nights->register('n-1', 'm-2');

        $this->observed->take();

        $this->nights->addTable('n-1', new Table('t-2', 1));

        self::assertEquals([new SeatOffered('n-1', 'm-2')], $this->observed->take());
    }

    public function testRemovingTheLastTableOfAnEmptyNightIsNotFull(): void
    {
        $this->published();

        $night = $this->nights->removeTable('n-1', 't-1');

        self::assertSame(0, $night->capacity());
        self::assertSame([], $this->observed->take());
    }

    public function testSignUpsCloseWhenTheNightStarts(): void
    {
        $this->published();
        $this->nights->register('n-1', 'm-1');
        $this->clock->modify('2027-03-05 18:59 UTC');
        $this->nights->seat('n-1', 'm-1', 't-1');
        $this->clock->modify('2027-03-05 19:00 UTC');

        foreach ([
            fn (): mixed => $this->nights->register('n-1', 'm-2'),
            fn (): mixed => $this->nights->withdraw('n-1', 'm-1'),
            fn (): mixed => $this->nights->seat('n-1', 'm-1', null),
            fn (): mixed => $this->nights->propose('n-1', ['g-1']),
            fn (): mixed => $this->nights->vote('n-1', 'm-1', 'g-1'),
            fn (): mixed => $this->nights->withdrawVote('n-1', 'm-1'),
        ] as $change) {
            try {
                $change();
                self::fail('A night changed after it started.');
            } catch (NightRefused $refused) {
                self::assertSame('The night has started.', $refused->getMessage());
            }
        }

        self::assertSame('t-1', $this->nights->get('n-1')->registration('m-1')?->table);
    }

    public function testANightIsCancelledUntilItEnds(): void
    {
        $this->published();
        $this->clock->modify('2027-03-05 22:59 UTC');

        self::assertSame(NightStatus::Cancelled, $this->nights->cancel('n-1')->status);
        self::assertEquals([new NightCancelled('n-1')], $this->observed->take());
    }

    public function testADraftNightCancelledIsNotToldAsPublished(): void
    {
        $this->nights->plan('n-1', new DateTimeImmutable('2027-03-05 19:00 UTC'), new DateTimeImmutable('2027-03-05 23:00 UTC'), 'venue-1', 'host-1', [new Table('t-1', 2)]);
        $this->observed->take();

        $this->nights->cancel('n-1');

        self::assertEquals([new NightCancelled('n-1')], $this->observed->take());
    }

    public function testAWithdrawalThatOffersASeatAndFillsTheNightTellsBoth(): void
    {
        $this->published();
        $this->nights->register('n-1', 'm-1');
        $this->nights->register('n-1', 'm-2', true);

        $this->observed->take();

        $this->nights->withdraw('n-1', 'm-1');

        self::assertEquals([new SeatOffered('n-1', 'm-2'), new NightFull('n-1')], $this->observed->take());
    }

    public function testAnEndedNightIsNotCancelled(): void
    {
        $this->published();
        $this->clock->modify('2027-03-05 23:00 UTC');

        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The night has ended.');

        $this->nights->cancel('n-1');
    }

    public function testTheGamesProposedAreVotedAndCounted(): void
    {
        $this->published(4);
        $this->nights->register('n-1', 'm-1');
        $this->nights->register('n-1', 'm-2');
        $this->nights->register('n-1', 'm-3');
        $this->nights->propose('n-1', ['g-1', 'g-2']);
        $this->nights->vote('n-1', 'm-1', 'g-2');
        $this->nights->vote('n-1', 'm-2', 'g-2');
        $this->nights->vote('n-1', 'm-3', 'g-1');
        $this->nights->withdrawVote('n-1', 'm-3');

        $result = $this->nights->result('n-1');

        self::assertSame(VoteOutcome::Winner, $result->outcome);
        self::assertSame('g-2', $result->winner());
        self::assertSame(2, $result->votes);
    }

    public function testAGameProposedIsInTheCatalogue(): void
    {
        $this->published();

        $this->expectException(EntryNotFound::class);

        $this->nights->propose('n-1', ['g-1', 'g-9']);
    }

    public function testAHiddenGameIsNotProposed(): void
    {
        $this->published();

        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The game g-hidden is hidden in the catalogue.');

        $this->nights->propose('n-1', ['g-hidden']);
    }

    public function testTooManyGamesAreNotLookedUp(): void
    {
        $this->published();

        $this->expectException(InvalidNight::class);
        $this->expectExceptionMessageIs('A night proposes at most 30 games to the vote.');

        $this->nights->propose('n-1', array_map(static fn (int $i): string => 'g-x'.$i, range(0, 30)));
    }

    public function testThirtyGamesAreProposed(): void
    {
        $this->published();
        $games = array_map(static fn (int $i): string => 'g-x'.$i, range(1, 30));

        foreach ($games as $game) {
            $this->catalog->add($game, new Details('Game '.$game));
        }

        self::assertSame($games, $this->nights->propose('n-1', $games)->candidates);
    }

    public function testTheServiceWorksWithoutAnObserver(): void
    {
        $nights = new GameNights(new InMemoryNightStore(), $this->clock, $this->catalog);
        $nights->plan('n-1', new DateTimeImmutable('2027-03-05 19:00 UTC'), new DateTimeImmutable('2027-03-05 23:00 UTC'), 'venue-1', 'host-1', [new Table('t-1', 1)]);
        $nights->publish('n-1');

        self::assertSame(RegistrationStatus::Confirmed, $nights->register('n-1', 'm-1')->status);
        self::assertSame(NightStatus::Cancelled, $nights->cancel('n-1')->status);
    }
}
