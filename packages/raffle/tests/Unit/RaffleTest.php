<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Raffle\Beacon\BeaconVerification;
use Tilttek\Raffle\Exception\InvalidRaffle;
use Tilttek\Raffle\Raffle;
use Tilttek\Raffle\RaffleStatus;
use Tilttek\Raffle\SeedSource;
use Tilttek\Raffle\Stake;
use Tilttek\Raffle\Tests\Unit\Fixture\Draws;
use Tilttek\Raffle\Tests\Unit\Fixture\FakeBeacon;
use Tilttek\Raffle\Tests\Unit\Fixture\FrozenClock;

#[CoversClass(Raffle::class)]
final class RaffleTest extends TestCase
{
    public function testARaffleOpensAtVersionOneWithoutEntrants(): void
    {
        $raffle = Raffle::open('door-prize', Stake::None, SeedSource::CommitReveal, 3, 2, new FrozenClock()->now, str_repeat('0', 64));

        self::assertSame(RaffleStatus::Open, $raffle->status);
        self::assertSame(1, $raffle->version);
        self::assertSame(0, $raffle->entrantCount);
        self::assertSame(hash('sha256', str_repeat('0', 64)), $raffle->commitment);
        self::assertSame(0, $raffle->draws());
        self::assertSame(1, $raffle->entered()->draws());
        self::assertSame(2, $raffle->entered()->version);
        self::assertSame(5, $raffle->entered()->entered()->entered()->entered()->entered()->entered()->draws());
        self::assertSame(hash('sha256', 'abc'), Raffle::commit('abc'));
    }

    public function testTheNumbersOfARaffleAreBounded(): void
    {
        $at = new FrozenClock()->now;
        self::assertSame(1000, Raffle::open('a', Stake::Prize, SeedSource::Beacon, 1000, 1000, $at)->winners);

        foreach ([[0, 0], [1001, 0], [1, -1], [1, 1001]] as [$winners, $alternates]) {
            try {
                Raffle::open('a', Stake::Prize, SeedSource::Beacon, $winners, $alternates, $at);
                self::fail('A raffle out of range was opened.');
            } catch (InvalidRaffle $invalid) {
                self::assertSame('A raffle draws 1 to 1000 winners and 0 to 1000 alternates.', $invalid->getMessage());
            }
        }
    }

    public function testOnlyACommitRevealRaffleHoldsASeed(): void
    {
        $at = new FrozenClock()->now;

        foreach ([[SeedSource::CommitReveal, null], [SeedSource::Beacon, str_repeat('0', 64)]] as [$source, $seed]) {
            try {
                Raffle::open('a', Stake::None, $source, 1, 0, $at, $seed);
                self::fail('A seed was given or missing.');
            } catch (InvalidRaffle $invalid) {
                self::assertSame('A raffle seeded by commit-reveal holds its seed, and only it does.', $invalid->getMessage());
            }
        }

        $this->expectException(InvalidRaffle::class);
        Raffle::open('a', Stake::None, SeedSource::CommitReveal, 1, 0, $at, 'short');
    }

    public function testARaffleReadsBackAtEveryStep(): void
    {
        $draws = new Draws();
        $draws->raffles->open('open', Stake::None, 2, 1);
        $draws->raffles->enter('open', 'm-1');
        $draws->prize();
        $draws->noStake();
        $draws->raffles->open('sealed', Stake::Prize);
        $draws->raffles->enter('sealed', 'm-1');
        $draws->raffles->seal('sealed');

        foreach (['open', 'door-prize', 'first-speaker', 'sealed'] as $id) {
            $raffle = $draws->raffles->raffle($id);

            self::assertEquals($raffle, Raffle::fromArray($raffle->toArray()));
            $decoded = json_decode(json_encode($raffle->toArray(), \JSON_THROW_ON_ERROR), true, 8, \JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded);
            self::assertEquals($raffle, Raffle::fromArray($decoded));
        }

        $prize = $draws->raffles->raffle('door-prize')->toArray();
        self::assertSame(['hash' => str_repeat('ab', 32), 'genesis' => 1767225600, 'period' => 3, 'scheme' => 'test-scheme', 'public_key' => str_repeat('cd', 48)], $prize['chain']);
        self::assertSame(9_096_012, $prize['round']);
        self::assertSame(['round' => 9_096_012, 'randomness' => FakeBeacon::round(9_096_012)->randomness, 'signature' => FakeBeacon::round(9_096_012)->signature, 'previous_signature' => null, 'verification' => 'relay-quorum'], $prize['beacon']);
        self::assertSame('2026-11-12T20:01:00.000000Z', $prize['drawn_at']);
        self::assertSame('2026-11-12T20:00:00.000000Z', $prize['sealed_at']);
        self::assertSame(['id', 'stake', 'source', 'winners', 'alternates', 'status', 'version', 'opened_at', 'entrant_count', 'seed', 'sealed_at', 'entrants_hash', 'chain', 'round', 'beacon', 'drawn_at'], array_keys($prize));
        self::assertNull($draws->raffles->raffle('open')->toArray()['sealed_at']);
        self::assertNull($draws->raffles->raffle('open')->toArray()['drawn_at']);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function unreadable(): iterable
    {
        $draws = new Draws();
        $draws->prize();

        $prize = $draws->raffles->raffle('door-prize')->toArray();
        $draws->noStake();
        $noStake = $draws->raffles->raffle('first-speaker')->toArray();
        $round = FakeBeacon::round(9_096_012);
        $beacon = ['round' => $round->round, 'randomness' => $round->randomness, 'signature' => $round->signature, 'previous_signature' => null, 'verification' => 'relay-quorum'];
        $open = Raffle::open('open', Stake::None, SeedSource::CommitReveal, 1, 0, new FrozenClock()->now, str_repeat('0', 64))->toArray();

        yield 'no stake' => [[...$prize, 'stake' => 'big'], 'A raffle holds its identifier'];
        yield 'a stake not text' => [[...$prize, 'stake' => 1], 'A raffle holds its identifier'];
        yield 'no source' => [[...$prize, 'source' => 'dice'], 'A raffle holds its identifier'];
        yield 'a source not text' => [[...$prize, 'source' => 1], 'A raffle holds its identifier'];
        yield 'no status' => [[...$prize, 'status' => 'gone'], 'A raffle holds its identifier'];
        yield 'a status not text' => [[...$prize, 'status' => 1], 'A raffle holds its identifier'];
        yield 'an identifier not text' => [[...$prize, 'id' => 1], 'A raffle holds its identifier'];
        yield 'winners not a number' => [[...$prize, 'winners' => '2'], 'A raffle holds its identifier'];
        yield 'alternates not a number' => [[...$prize, 'alternates' => '1'], 'A raffle holds its identifier'];
        yield 'a version not a number' => [[...$prize, 'version' => '1'], 'A raffle holds its identifier'];
        yield 'an opening time not text' => [[...$prize, 'opened_at' => 1], 'A raffle holds its identifier'];
        yield 'a count not a number' => [[...$prize, 'entrant_count' => '1'], 'A raffle holds its identifier'];
        yield 'a seed not text' => [[...$noStake, 'seed' => 1], 'A raffle holds its identifier'];
        yield 'version zero' => [[...$prize, 'version' => 0], 'A raffle is at version 1 or more'];
        yield 'a negative count' => [[...$open, 'entrant_count' => -1], 'A raffle is at version 1 or more'];
        yield 'too many entrants' => [[...$open, 'entrant_count' => 100_001], 'A raffle is at version 1 or more'];
        yield 'sealed without a time' => [[...$prize, 'sealed_at' => null], 'A sealed raffle holds'];
        yield 'sealed without a hash' => [[...$prize, 'entrants_hash' => null], 'A sealed raffle holds'];
        yield 'sealed without an entrant' => [[...$prize, 'entrant_count' => 0], 'A sealed raffle holds'];
        yield 'a bad hash' => [[...$prize, 'entrants_hash' => 'x'], 'The entrants hash is 64'];
        yield 'no chain' => [[...$prize, 'chain' => null], 'A sealed raffle seeded by a beacon holds the chain and the round'];
        yield 'no round' => [[...$prize, 'round' => null], 'A sealed raffle seeded by a beacon holds the chain and the round'];
        yield 'round zero' => [[...$prize, 'round' => 0], 'A beacon round is 1'];
        yield 'a chain without its hash' => [[...$prize, 'chain' => ['genesis' => 1, 'period' => 3, 'scheme' => 's']], 'A beacon chain holds'];
        yield 'a chain with a key not text' => [[...$prize, 'chain' => ['hash' => str_repeat('ab', 32), 'genesis' => 1, 'period' => 3, 'scheme' => 's', 'public_key' => 1]], 'A beacon chain holds'];
        yield 'a chain with a genesis not a number' => [[...$prize, 'chain' => ['hash' => str_repeat('ab', 32), 'genesis' => '1', 'period' => 3, 'scheme' => 's']], 'A beacon chain holds'];
        yield 'a chain with a period not a number' => [[...$prize, 'chain' => ['hash' => str_repeat('ab', 32), 'genesis' => 1, 'period' => '3', 'scheme' => 's']], 'A beacon chain holds'];
        yield 'a chain with a scheme not text' => [[...$prize, 'chain' => ['hash' => str_repeat('ab', 32), 'genesis' => 1, 'period' => 3, 'scheme' => 1]], 'A beacon chain holds'];
        yield 'drawn without a beacon' => [[...$prize, 'beacon' => null], 'A raffle drawn from a beacon holds the round it drew from'];
        yield 'a beacon without its round' => [[...$prize, 'beacon' => [...$beacon, 'round' => null]], 'A beacon round holds'];
        yield 'a beacon without its randomness' => [[...$prize, 'beacon' => [...$beacon, 'randomness' => null]], 'A beacon round holds'];
        yield 'a beacon without its signature' => [[...$prize, 'beacon' => [...$beacon, 'signature' => null]], 'A beacon round holds'];
        yield 'a beacon with a previous signature not text' => [[...$prize, 'beacon' => [...$beacon, 'previous_signature' => 1]], 'A beacon round holds'];
        yield 'a beacon without its verification' => [[...$prize, 'beacon' => [...$beacon, 'verification' => 'trust']], 'A beacon round holds'];
        yield 'a beacon with a verification not text' => [[...$prize, 'beacon' => [...$beacon, 'verification' => 1]], 'A beacon round holds'];
        yield 'drawn without a time' => [[...$prize, 'drawn_at' => null], 'A drawn raffle holds the time it was drawn'];
        yield 'sealed with a draw time' => [[...$prize, 'status' => 'sealed', 'beacon' => null], 'A drawn raffle holds the time it was drawn'];
        yield 'a bad time' => [[...$prize, 'drawn_at' => 'yesterday'], 'A time is written in UTC'];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('unreadable')]
    public function testDataThatDoesNotReadAsARaffleIsRefused(array $data, string $message): void
    {
        $this->expectException(InvalidRaffle::class);
        $this->expectExceptionMessageIsOrContains($message);

        Raffle::fromArray($data);
    }

    public function testAMissingFieldIsRefused(): void
    {
        $this->expectException(InvalidRaffle::class);
        $this->expectExceptionMessageIsOrContains('A raffle holds its identifier');

        Raffle::fromArray([]);
    }

    public function testABeaconOfAProofIsRead(): void
    {
        $beacon = FakeBeacon::round(3, BeaconVerification::Signature);

        self::assertEquals($beacon, Raffle::beaconOf(['round' => 3, 'randomness' => $beacon->randomness, 'signature' => $beacon->signature, 'verification' => 'signature']));
    }

    public function testTheCountsAndTheChainOfAProofAreReadByTheRaffle(): void
    {
        Raffle::checkCounts(1, 0);
        Raffle::checkCounts(1000, 1000);

        $chain = Raffle::chainOf(['hash' => str_repeat('ab', 32), 'genesis' => 1, 'period' => 3, 'scheme' => 's', 'public_key' => null]);

        self::assertSame(3, $chain->period);
        self::assertNull($chain->publicKey);
    }

    public function testARaffleAtVersionOneWithTheMostEntrantsReadsBack(): void
    {
        $open = Raffle::open('open', Stake::None, SeedSource::CommitReveal, 1, 0, new FrozenClock()->now, str_repeat('0', 64))->toArray();

        self::assertSame(1, Raffle::fromArray($open)->version);
        self::assertSame(100_000, Raffle::fromArray([...$open, 'entrant_count' => 100_000])->entrantCount);
    }

    public function testABeaconWithoutItsRoundIsRefused(): void
    {
        $beacon = FakeBeacon::round(3);

        $this->expectException(InvalidRaffle::class);
        $this->expectExceptionMessageIsOrContains('A beacon round holds');

        Raffle::beaconOf(['randomness' => $beacon->randomness, 'signature' => $beacon->signature, 'verification' => 'signature']);
    }
}
