<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Raffle\Announcement;
use Tilttek\Raffle\Exception\InvalidRaffle;
use Tilttek\Raffle\RaffleProof;
use Tilttek\Raffle\Tests\Unit\Fixture\Draws;
use Tilttek\Raffle\Tests\Unit\Fixture\FakeBeacon;

#[CoversClass(RaffleProof::class)]
#[CoversClass(Announcement::class)]
final class RaffleProofTest extends TestCase
{
    public function testAProofOfABeaconDrawReadsBackFromItsJson(): void
    {
        $proof = new Draws()->prize();
        $data = $proof->toArray();

        self::assertEquals($proof, RaffleProof::fromJson($proof->toJson()));
        self::assertEquals($proof, RaffleProof::fromArray($data));
        self::assertSame(['version', 'raffle', 'stake', 'winners', 'alternates', 'entrants', 'entrants_hash', 'source', 'seed', 'drawn', 'sealed_at', 'drawn_at'], array_keys($data));
        self::assertSame('tilttek-raffle/1', $data['version']);
        self::assertSame('prize', $data['stake']);
        self::assertSame(['member-1', 'member-2', 'member-3', 'member-4', 'member-5', 'member-6'], $data['entrants']);
        self::assertSame([
            'kind' => 'beacon',
            'chain' => ['hash' => str_repeat('ab', 32), 'genesis' => 1767225600, 'period' => 3, 'scheme' => 'test-scheme', 'public_key' => str_repeat('cd', 48)],
            'beacon' => ['round' => 9_096_012, 'randomness' => FakeBeacon::round(9_096_012)->randomness, 'signature' => FakeBeacon::round(9_096_012)->signature, 'previous_signature' => null, 'verification' => 'relay-quorum'],
        ], $data['source']);
        self::assertSame('2026-11-12T20:00:00.000000Z', $data['sealed_at']);
        self::assertSame('2026-11-12T20:01:00.000000Z', $data['drawn_at']);
        self::assertStringStartsWith('{"version":"tilttek-raffle/1","raffle":"door-prize",', $proof->toJson());
        self::assertSame(\array_slice($proof->drawn, 0, 2), $proof->winnerList());
        self::assertSame(\array_slice($proof->drawn, 2), $proof->alternateList());
        self::assertCount(3, $proof->drawn);
    }

    public function testAProofOfACommitRevealDrawReadsBack(): void
    {
        $proof = new Draws()->noStake();
        $data = $proof->toArray();

        self::assertEquals($proof, RaffleProof::fromJson($proof->toJson()));
        self::assertSame(['kind' => 'commit-reveal', 'commitment' => $proof->commitment, 'seed' => $proof->revealedSeed], $data['source']);
        self::assertSame(hash('sha256', (string) $proof->revealedSeed), $proof->commitment);
        self::assertSame([$proof->drawn[0]], $proof->winnerList());
        self::assertSame([$proof->drawn[1], $proof->drawn[2]], $proof->alternateList());
    }

    public function testAnAnnouncementIsWrittenAsPlainValues(): void
    {
        $draws = new Draws();
        $draws->prize();
        $draws->noStake();

        $prize = $draws->raffles->announcement('door-prize')->toArray();
        $noStake = $draws->raffles->announcement('first-speaker')->toArray();

        self::assertSame(['raffle' => 'door-prize', 'stake' => 'prize', 'source' => 'beacon', 'winners' => 2, 'alternates' => 1, 'entrants' => 6, 'entrants_hash' => $prize['entrants_hash'], 'sealed_at' => '2026-11-12T20:00:00.000000Z', 'commitment' => null, 'chain' => str_repeat('ab', 32), 'round' => 9_096_012, 'drawable_at' => '2026-11-12T20:00:33.000000Z'], $prize);
        self::assertNull($noStake['chain']);
        self::assertNull($noStake['round']);
        self::assertNull($noStake['drawable_at']);
        self::assertNull($draws->raffles->announcement('first-speaker')->drawableAt());
        self::assertSame(64, \strlen((string) $noStake['commitment']));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function unreadable(): iterable
    {
        $prize = new Draws()->prize()->toArray();
        $noStake = new Draws()->noStake()->toArray();
        \assert(\is_array($noStake['source']) && \is_array($prize['source']));

        yield 'another version' => [[...$prize, 'version' => 'tilttek-raffle/2'], 'A proof of version tilttek-raffle/1 holds'];
        yield 'no stake' => [[...$prize, 'stake' => 'big'], 'A proof of version'];
        yield 'a stake not text' => [[...$prize, 'stake' => 1], 'A proof of version'];
        yield 'no source' => [[...$prize, 'source' => null], 'A proof of version'];
        yield 'a source of another kind' => [[...$prize, 'source' => ['kind' => 'dice']], 'A proof of version'];
        yield 'a kind not text' => [[...$prize, 'source' => ['kind' => 1]], 'A proof of version'];
        yield 'a raffle not text' => [[...$prize, 'raffle' => 1], 'A proof of version'];
        yield 'winners not a number' => [[...$prize, 'winners' => '2'], 'A proof of version'];
        yield 'alternates not a number' => [[...$prize, 'alternates' => '1'], 'A proof of version'];
        yield 'a hash not text' => [[...$prize, 'entrants_hash' => 1], 'A proof of version'];
        yield 'a seed not text' => [[...$prize, 'seed' => 1], 'A proof of version'];
        yield 'a closing time not text' => [[...$prize, 'sealed_at' => 1], 'A proof of version'];
        yield 'a draw time not text' => [[...$prize, 'drawn_at' => 1], 'A proof of version'];
        yield 'a bad raffle' => [[...$prize, 'raffle' => 'door prize'], 'A raffle identifier'];
        yield 'no winner' => [[...$prize, 'winners' => 0], 'A raffle draws 1 to 1000 winners'];
        yield 'entrants not a list' => [[...$prize, 'entrants' => ['a' => 'm-1']], 'The entrants of a proof are a list of at most 100000 entrants.'];
        yield 'entrants not an array' => [[...$prize, 'entrants' => 'm-1'], 'The entrants of a proof are a list'];
        yield 'too many entrants' => [[...$prize, 'entrants' => array_fill(0, 100_001, 'm')], 'The entrants of a proof are a list'];
        yield 'an entrant not text' => [[...$prize, 'entrants' => [1]], 'An entrant is text.'];
        yield 'a bad entrant' => [[...$prize, 'entrants' => ['ann@example.org']], 'An entrant is an opaque reference'];
        yield 'too many names drawn' => [[...$prize, 'drawn' => array_fill(0, 2001, 'm')], 'The names drawn of a proof are a list of at most 2000 entrants.'];
        yield 'a bad hash' => [[...$prize, 'entrants_hash' => 'x'], 'The entrants hash is 64'];
        yield 'a bad seed of the draw' => [[...$prize, 'seed' => 'x'], 'The seed of the draw is 64'];
        yield 'a bad time' => [[...$prize, 'drawn_at' => 'now'], 'A time is written in UTC'];
        yield 'no commitment' => [[...$noStake, 'source' => [...$noStake['source'], 'commitment' => null]], 'A source of commit-reveal holds its commitment.'];
        yield 'no seed revealed' => [[...$noStake, 'source' => [...$noStake['source'], 'seed' => null]], 'A source of commit-reveal holds its seed.'];
        yield 'a bad commitment' => [[...$noStake, 'source' => [...$noStake['source'], 'commitment' => 'x']], 'The commitment is 64'];
        yield 'a bad seed revealed' => [[...$noStake, 'source' => [...$noStake['source'], 'seed' => 'x']], 'The seed is 64'];
        yield 'no chain' => [[...$prize, 'source' => [...$prize['source'], 'chain' => null]], 'A beacon chain holds'];
        yield 'no beacon' => [[...$prize, 'source' => [...$prize['source'], 'beacon' => null]], 'A raffle drawn from a beacon holds the round'];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('unreadable')]
    public function testDataThatDoesNotReadAsAProofIsRefused(array $data, string $message): void
    {
        $this->expectException(InvalidRaffle::class);
        $this->expectExceptionMessageIsOrContains($message);

        RaffleProof::fromArray($data);
    }

    public function testTheMostEntrantsAndNamesDrawnStillRead(): void
    {
        $prize = new Draws()->prize()->toArray();
        $proof = RaffleProof::fromArray([...$prize, 'entrants' => array_fill(0, 100_000, 'm'), 'drawn' => array_fill(0, 2000, 'm')]);

        self::assertCount(100_000, $proof->entrants);
        self::assertCount(2000, $proof->drawn);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function unreadableJson(): iterable
    {
        yield 'not JSON' => ['{', 'A proof is a JSON object.'];
        yield 'not an object' => ['"door-prize"', 'A proof is a JSON object.'];
        yield 'too deep' => [str_repeat('[', 8).str_repeat(']', 8), 'A proof is a JSON object.'];
        yield 'too long' => [str_repeat(' ', RaffleProof::MAX_JSON + 1), 'A proof is at most 16777216 bytes of JSON.'];
        yield 'at the depth limit' => [str_repeat('[', 7).str_repeat(']', 7), 'A proof of version'];
        yield 'at the longest length' => ['[]'.str_repeat(' ', RaffleProof::MAX_JSON - 2), 'A proof of version'];
    }

    #[DataProvider('unreadableJson')]
    public function testJsonThatDoesNotReadAsAProofIsRefused(string $json, string $message): void
    {
        $this->expectException(InvalidRaffle::class);
        $this->expectExceptionMessageIsOrContains($message);

        RaffleProof::fromJson($json);
    }

    public function testAnAnnouncementWithOnlyAChainOrOnlyARoundIsNeverDrawable(): void
    {
        $draws = new Draws();
        $draws->prize();

        $announcement = $draws->raffles->announcement('door-prize');
        self::assertInstanceOf(DateTimeImmutable::class, $announcement->drawableAt());

        foreach ([[$announcement->chain, null], [null, $announcement->round]] as [$chain, $round]) {
            $partial = new Announcement($announcement->raffle, $announcement->stake, $announcement->source, $announcement->winners, $announcement->alternates, $announcement->entrants, $announcement->entrantsHash, $announcement->sealedAt, null, $chain, $round);

            self::assertNull($partial->drawableAt());
        }
    }
}
