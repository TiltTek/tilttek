<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Raffle\Announcement;
use Tilttek\Raffle\Beacon\BeaconVerification;
use Tilttek\Raffle\Entrants;
use Tilttek\Raffle\Event\RaffleDrawn;
use Tilttek\Raffle\Exception\BeaconRefused;
use Tilttek\Raffle\Exception\InvalidRaffle;
use Tilttek\Raffle\Exception\RaffleConflict;
use Tilttek\Raffle\Exception\RaffleRefused;
use Tilttek\Raffle\Raffle;
use Tilttek\Raffle\Raffles;
use Tilttek\Raffle\RaffleStatus;
use Tilttek\Raffle\RaffleVerifier;
use Tilttek\Raffle\SeedSource;
use Tilttek\Raffle\Selection;
use Tilttek\Raffle\Stake;
use Tilttek\Raffle\Store\InMemoryRaffleStore;
use Tilttek\Raffle\Tests\Unit\Fixture\FakeBeacon;
use Tilttek\Raffle\Tests\Unit\Fixture\FrozenClock;

#[CoversClass(Raffles::class)]
#[CoversClass(RaffleRefused::class)]
#[CoversClass(RaffleDrawn::class)]
final class RafflesTest extends TestCase
{
    private InMemoryRaffleStore $store;

    private FakeBeacon $beacon;

    private FrozenClock $clock;

    /** @var list<object> */
    private array $dispatched = [];

    private Raffles $raffles;

    protected function setUp(): void
    {
        $this->store = new InMemoryRaffleStore();
        $this->beacon = new FakeBeacon();
        $this->clock = new FrozenClock();
        $events = self::createStub(EventDispatcherInterface::class);
        $events->method('dispatch')->willReturnCallback(function (object $event): object {
            $this->dispatched[] = $event;

            return $event;
        });
        $this->raffles = new Raffles($this->store, $this->beacon, $events, $this->clock, new Randomizer(new Xoshiro256StarStar(3)));
    }

    public function testAPrizeDrawTakesItsSeedFromABeaconRoundAfterTheEntriesClose(): void
    {
        $raffle = $this->raffles->open('door-prize', Stake::Prize, winners: 2, alternates: 1);

        self::assertSame(SeedSource::Beacon, $raffle->source);
        self::assertNull($raffle->commitment);
        self::assertNull($raffle->seed);
        self::assertEquals($this->clock->now, $raffle->openedAt);

        foreach (['m-1', 'm-2', 'm-3', 'm-4', 'm-5'] as $entrant) {
            $this->raffles->enter('door-prize', $entrant);
        }

        $announcement = $this->raffles->seal('door-prize');
        $chain = FakeBeacon::chainOfTests();
        // 2026-11-12 20:00:00 is 27 288 000 seconds after the genesis: round 9 096 001 is current, 30 seconds later
        // round 9 096 011, so the first round after the margin is 9 096 012.
        self::assertSame(9_096_012, $announcement->round);
        self::assertEquals($chain, $announcement->chain);
        self::assertSame(5, $announcement->entrants);
        self::assertSame(Entrants::hash('door-prize', ['m-1', 'm-2', 'm-3', 'm-4', 'm-5']), $announcement->entrantsHash);
        self::assertEquals($this->clock->now, $announcement->sealedAt);
        self::assertEquals($announcement, $this->raffles->announcement('door-prize'));
        self::assertSame(RaffleStatus::Sealed, $this->raffles->raffle('door-prize')->status);

        $this->clock->move('+33 seconds');
        $proof = $this->raffles->draw('door-prize');

        self::assertSame([9_096_012], $this->beacon->fetched);
        self::assertEquals(FakeBeacon::round(9_096_012), $proof->beacon);
        self::assertSame(Entrants::seed('door-prize', $announcement->entrantsHash, FakeBeacon::round(9_096_012)->randomness), $proof->seed);
        self::assertSame(Selection::draw($proof->seed, ['m-1', 'm-2', 'm-3', 'm-4', 'm-5'], 3), $proof->drawn);
        self::assertCount(2, $proof->winnerList());
        self::assertCount(1, $proof->alternateList());
        self::assertEquals($this->clock->now, $proof->drawnAt);
        self::assertSame([], new RaffleVerifier()->problems($proof));
        self::assertEquals($proof, $this->raffles->proof('door-prize'));
        self::assertEquals([new RaffleDrawn('door-prize')], $this->dispatched);
        self::assertSame(RaffleStatus::Drawn, $this->raffles->raffle('door-prize')->status);
    }

    public function testADrawWithoutAStakeCommitsToTheOrganisersSeedWhenItOpens(): void
    {
        $raffle = $this->raffles->open('first-speaker', Stake::None);

        self::assertSame(SeedSource::CommitReveal, $raffle->source);
        self::assertSame(bin2hex(new Randomizer(new Xoshiro256StarStar(3))->getBytes(32)), $raffle->seed);
        self::assertSame(hash('sha256', $raffle->seed), $raffle->commitment);

        $this->raffles->enter('first-speaker', 'seat-2');
        $this->raffles->enter('first-speaker', 'seat-1');

        $announcement = $this->raffles->seal('first-speaker');

        self::assertSame($raffle->commitment, $announcement->commitment);
        self::assertNull($announcement->chain);
        self::assertNull($announcement->round);

        $proof = $this->raffles->draw('first-speaker');

        self::assertSame([], $this->beacon->fetched);
        self::assertSame($raffle->seed, $proof->revealedSeed);
        self::assertSame(['seat-1', 'seat-2'], $proof->entrants);
        self::assertCount(1, $proof->drawn);
        self::assertTrue(new RaffleVerifier()->holds($proof));
    }

    public function testADrawWithoutAStakeMayAskForTheBeacon(): void
    {
        self::assertSame(SeedSource::Beacon, $this->raffles->open('fun', Stake::None, source: SeedSource::Beacon)->source);
    }

    public function testFewerEntrantsThanWinnersAreAllDrawn(): void
    {
        $this->raffles->open('small', Stake::None, winners: 3, alternates: 2);
        $this->raffles->enter('small', 'a');
        $this->raffles->enter('small', 'b');
        $this->raffles->seal('small');

        self::assertCount(2, $this->raffles->draw('small')->drawn);
    }

    public function testAPrizeIsNeverSeededByItsOrganiser(): void
    {
        $this->expectException(RaffleRefused::class);
        $this->expectExceptionMessageIsOrContains('A raffle with a prize takes its seed from a public beacon round, never from a seed of its organiser.');

        $this->raffles->open('door-prize', Stake::Prize, source: SeedSource::CommitReveal);
    }

    public function testABeaconDrawNeedsABeacon(): void
    {
        $this->expectException(RaffleRefused::class);
        $this->expectExceptionMessageIsOrContains('A raffle seeded by a beacon needs the beacon: none is configured.');

        new Raffles($this->store)->open('door-prize', Stake::Prize);
    }

    public function testARaffleIsOpenedOnce(): void
    {
        $this->raffles->open('door-prize', Stake::Prize);

        $this->expectException(RaffleRefused::class);
        $this->expectExceptionMessageIsOrContains('A raffle has the identifier door-prize already.');

        $this->raffles->open('door-prize', Stake::None);
    }

    public function testAnEntrantHoldsOneChance(): void
    {
        $this->raffles->open('door-prize', Stake::Prize);
        $this->raffles->enter('door-prize', 'm-1');

        try {
            $this->raffles->enter('door-prize', 'm-1');
            self::fail('An entrant entered twice.');
        } catch (RaffleRefused $raffleRefused) {
            self::assertSame('m-1 has entered the raffle door-prize already: an entrant holds one chance, never more.', $raffleRefused->getMessage());
        }

        self::assertSame(['m-1'], $this->store->entrants('door-prize'));
        self::assertSame(1, $this->raffles->raffle('door-prize')->entrantCount);
        self::assertSame(2, $this->raffles->raffle('door-prize')->version);
    }

    public function testAnEntrantIsAnOpaqueReference(): void
    {
        $this->raffles->open('door-prize', Stake::Prize);

        $this->expectException(InvalidRaffle::class);

        $this->raffles->enter('door-prize', 'ann@example.org');
    }

    public function testAnUnknownRaffleIsRefused(): void
    {
        $this->expectException(RaffleRefused::class);
        $this->expectExceptionMessageIsOrContains('No raffle has the identifier ghost.');

        $this->raffles->enter('ghost', 'm-1');
    }

    public function testNoEntryComesOnceTheEntriesAreClosed(): void
    {
        $this->raffles->open('door-prize', Stake::None);
        $this->raffles->enter('door-prize', 'm-1');
        $this->raffles->seal('door-prize');

        $this->expectException(RaffleRefused::class);
        $this->expectExceptionMessageIsOrContains('The entries of the raffle door-prize are closed.');

        $this->raffles->enter('door-prize', 'm-2');
    }

    public function testAFullRaffleTakesNoEntry(): void
    {
        $this->store->add(Raffle::fromArray([...Raffle::open('full', Stake::None, SeedSource::CommitReveal, 1, 0, $this->clock->now, str_repeat('0', 64))->toArray(), 'entrant_count' => Raffle::MAX_ENTRANTS]));

        $this->expectException(RaffleRefused::class);
        $this->expectExceptionMessageIsOrContains('The raffle full holds 100000 entrants, the most it takes.');

        $this->raffles->enter('full', 'm-1');
    }

    public function testARaffleOneBelowTheLimitStillTakesAnEntry(): void
    {
        $this->store->add(Raffle::fromArray([...Raffle::open('almost', Stake::None, SeedSource::CommitReveal, 1, 0, $this->clock->now, str_repeat('0', 64))->toArray(), 'entrant_count' => Raffle::MAX_ENTRANTS - 1]));
        $this->raffles->enter('almost', 'm-1');

        self::assertSame(Raffle::MAX_ENTRANTS, $this->raffles->raffle('almost')->entrantCount);
    }

    public function testARaffleWithoutEntrantsIsNotSealed(): void
    {
        $this->raffles->open('empty', Stake::None);

        $this->expectException(RaffleRefused::class);
        $this->expectExceptionMessageIsOrContains('The raffle empty has no entrant to draw from.');

        $this->raffles->seal('empty');
    }

    public function testARaffleIsSealedOnce(): void
    {
        $this->raffles->open('door-prize', Stake::None);
        $this->raffles->enter('door-prize', 'm-1');
        $this->raffles->seal('door-prize');

        $this->expectException(RaffleRefused::class);
        $this->expectExceptionMessageIsOrContains('The entries of the raffle door-prize are closed.');

        $this->raffles->seal('door-prize');
    }

    public function testAnOpenRaffleIsNeitherDrawnNorAnnouncedNorProven(): void
    {
        $this->raffles->open('door-prize', Stake::None);

        $steps = [
            'is not sealed: close its entries' => fn (): object => $this->raffles->draw('door-prize'),
            'is not sealed' => fn (): object => $this->raffles->announcement('door-prize'),
            'is not drawn yet' => fn (): object => $this->raffles->proof('door-prize'),
        ];

        foreach ($steps as $message => $step) {
            try {
                $step();
                self::fail('A step was allowed on an open raffle: '.$message);
            } catch (RaffleRefused $refused) {
                self::assertStringContainsString($message, $refused->getMessage());
            }
        }
    }

    public function testARaffleIsDrawnOnce(): void
    {
        $this->raffles->open('door-prize', Stake::None);
        $this->raffles->enter('door-prize', 'm-1');
        $this->raffles->seal('door-prize');
        $this->raffles->draw('door-prize');

        $this->expectException(RaffleRefused::class);
        $this->expectExceptionMessageIsOrContains('The raffle door-prize was drawn already; a raffle is drawn once.');

        $this->raffles->draw('door-prize');
    }

    public function testABeaconDrawWaitsForItsRound(): void
    {
        $this->raffles->open('door-prize', Stake::Prize);
        $this->raffles->enter('door-prize', 'm-1');
        $this->raffles->seal('door-prize');
        // Round 9 096 012 is published 33 seconds after the closing.
        $this->clock->move('+32 seconds');

        try {
            $this->raffles->draw('door-prize');
            self::fail('The raffle was drawn before its round.');
        } catch (RaffleRefused $raffleRefused) {
            self::assertSame('The raffle door-prize draws from the beacon round 9096012, published at 2026-11-12T20:00:33Z: wait for it.', $raffleRefused->getMessage());
        }

        self::assertSame([], $this->beacon->fetched);
        $this->clock->move('+1 second');
        $this->raffles->draw('door-prize');

        self::assertSame(RaffleStatus::Drawn, $this->raffles->raffle('door-prize')->status);
    }

    public function testABeaconThatGivesAnotherRoundIsRefusedAndNothingIsDrawn(): void
    {
        $this->raffles->open('door-prize', Stake::Prize);
        $this->raffles->enter('door-prize', 'm-1');
        $this->raffles->seal('door-prize');

        $this->clock->move('+1 hour');
        $this->beacon->instead = FakeBeacon::round(5);

        try {
            $this->raffles->draw('door-prize');
            self::fail('A round other than the one announced was drawn from.');
        } catch (BeaconRefused $beaconRefused) {
            self::assertSame('The beacon gave round 5 for round 9096012.', $beaconRefused->getMessage());
        }

        self::assertSame(RaffleStatus::Sealed, $this->raffles->raffle('door-prize')->status);
        self::assertSame([], $this->dispatched);
    }

    public function testEntrantsChangedAfterTheClosingStopTheDraw(): void
    {
        $this->raffles->open('door-prize', Stake::None);
        $this->raffles->enter('door-prize', 'm-1');

        $sealed = $this->raffles->seal('door-prize');
        $raffle = $this->raffles->raffle('door-prize');
        self::assertSame($sealed->entrantsHash, $raffle->entrantsHash);
        $this->store->enter($raffle->sealed($sealed->entrantsHash, $this->clock->now), 'm-2');

        $this->expectException(RaffleConflict::class);
        $this->expectExceptionMessageIsOrContains('The entrants the store keeps for the raffle door-prize no longer hash to the hash announced when its entries closed.');

        $this->raffles->draw('door-prize');
    }

    public function testADrawWithoutAnEventDispatcherStillDraws(): void
    {
        $raffles = new Raffles($this->store, clock: $this->clock);
        $raffles->open('quiet', Stake::None);
        $raffles->enter('quiet', 'm-1');
        $raffles->seal('quiet');

        self::assertSame(['m-1'], $raffles->draw('quiet')->drawn);
        self::assertSame(64, \strlen((string) $raffles->raffle('quiet')->seed));
    }

    public function testTheMarginIsBounded(): void
    {
        $raffles = new Raffles($this->store, $this->beacon, clock: $this->clock, margin: 0);
        $raffles->open('now', Stake::Prize);
        $raffles->enter('now', 'm-1');

        self::assertSame(9_096_002, $raffles->seal('now')->round);
        self::assertInstanceOf(Raffles::class, new Raffles($this->store, margin: 86_400));

        foreach ([-1, 86_401] as $margin) {
            try {
                new Raffles($this->store, margin: $margin);
                self::fail('A margin of '.$margin.' seconds was taken.');
            } catch (InvalidRaffle $invalid) {
                self::assertSame('The margin before the beacon round is 0 to 86400 seconds.', $invalid->getMessage());
            }
        }
    }

    public function testTheDefaultClockIsTheSystems(): void
    {
        $raffles = new Raffles($this->store);
        $before = new DateTimeImmutable('-1 second');
        $raffle = $raffles->open('now', Stake::None);

        self::assertGreaterThanOrEqual($before, $raffle->openedAt);
        self::assertLessThanOrEqual(new DateTimeImmutable('+1 second'), $raffle->openedAt);
    }

    public function testAnAnnouncementTellsWhenTheRaffleMayBeDrawn(): void
    {
        $this->raffles->open('door-prize', Stake::Prize, 1, 0);
        $this->raffles->enter('door-prize', 'm-1');

        $announcement = $this->raffles->seal('door-prize');

        self::assertEquals(new DateTimeImmutable('2026-11-12 20:00:33 UTC'), $announcement->drawableAt());
        self::assertInstanceOf(Announcement::class, $announcement);
        self::assertSame(BeaconVerification::RelayQuorum, FakeBeacon::round(1)->verification);
    }

    public function testTheDefaultMarginIsThirtySeconds(): void
    {
        $this->clock->move('+2 seconds');
        $this->raffles->open('margin', Stake::Prize);
        $this->raffles->enter('margin', 'm-1');

        self::assertSame(9_096_012, $this->raffles->seal('margin')->round);
    }

    public function testTheAnnouncementAndTheProofGiveTheTimeTheEntriesClosed(): void
    {
        $this->raffles->open('late', Stake::Prize);
        $this->raffles->enter('late', 'm-1');

        $this->clock->move('+5 minutes');
        $announcement = $this->raffles->seal('late');
        $this->clock->move('+5 minutes');
        $proof = $this->raffles->draw('late');

        self::assertSame('2026-11-12T20:05:00.000000Z', $announcement->toArray()['sealed_at']);
        self::assertSame('2026-11-12T20:05:00.000000Z', $this->raffles->announcement('late')->toArray()['sealed_at']);
        self::assertSame('2026-11-12T20:05:00.000000Z', $proof->toArray()['sealed_at']);
        self::assertSame('2026-11-12T20:10:00.000000Z', $proof->toArray()['drawn_at']);
    }

    public function testABeaconRaffleIsNeitherSealedNorDrawnWithoutABeacon(): void
    {
        $this->raffles->open('bare', Stake::Prize);
        $this->raffles->enter('bare', 'm-1');

        $bare = new Raffles($this->store, clock: $this->clock);

        try {
            $bare->seal('bare');
            self::fail('A beacon raffle was sealed without a beacon.');
        } catch (RaffleRefused $raffleRefused) {
            self::assertSame(RaffleRefused::noBeacon()->getMessage(), $raffleRefused->getMessage());
        }

        $this->raffles->seal('bare');
        $this->clock->move('+1 minute');

        $this->expectException(RaffleRefused::class);
        $this->expectExceptionMessageIsOrContains(RaffleRefused::noBeacon()->getMessage());

        $bare->draw('bare');
    }

    public function testASealedRaffleHoldsTheChainAndTheRoundTogetherOrNotAtAll(): void
    {
        foreach ([[FakeBeacon::chainOfTests(), null], [null, 5]] as $index => [$chain, $round]) {
            $id = 'half-'.$index;
            $this->raffles->open($id, Stake::None);
            $this->raffles->enter($id, 'a');
            $this->store->save($this->raffles->raffle($id)->sealed(Entrants::hash($id, ['a']), $this->clock->now, $chain, $round));

            self::assertSame(['a'], $this->raffles->draw($id)->drawn);
        }

        self::assertSame([], $this->beacon->fetched);
    }

    public function testARaffleDrawnWithoutItsHashIsAConflict(): void
    {
        $open = Raffle::open('hashless', Stake::None, SeedSource::CommitReveal, 1, 0, $this->clock->now, str_repeat('0', 64));
        $this->store->add($open->drawn($this->clock->now));

        $this->expectException(RaffleConflict::class);

        $this->raffles->proof('hashless');
    }

    public function testARaffleDrawnWithoutABeaconNorASeedHasNoNames(): void
    {
        $open = Raffle::open('seedless', Stake::None, SeedSource::Beacon, 1, 0, $this->clock->now);
        $this->store->add($open->sealed(Entrants::hash('seedless', []), $this->clock->now, FakeBeacon::chainOfTests(), 5)->drawn($this->clock->now));

        self::assertSame([], $this->raffles->proof('seedless')->drawn);
    }
}
