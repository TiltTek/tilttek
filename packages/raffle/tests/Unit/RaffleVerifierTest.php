<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Raffle\Beacon\Beacon;
use Tilttek\Raffle\Entrants;
use Tilttek\Raffle\RaffleProof;
use Tilttek\Raffle\RaffleVerifier;
use Tilttek\Raffle\Selection;
use Tilttek\Raffle\Tests\Unit\Fixture\Draws;
use Tilttek\Raffle\Tests\Unit\Fixture\FakeBeacon;
use Tilttek\Raffle\Tests\Unit\Fixture\RecordingVerifier;

#[CoversClass(RaffleVerifier::class)]
final class RaffleVerifierTest extends TestCase
{
    public function testHonestProofsHold(): void
    {
        $verifier = new RaffleVerifier();

        foreach ([new Draws()->prize(), new Draws()->noStake(), new Draws()->prize(1), new Draws()->noStake(1)] as $proof) {
            self::assertSame([], $verifier->problems($proof));
            self::assertTrue($verifier->holds($proof));
            self::assertSame([], $verifier->problemsOfJson($proof->toJson()));
        }
    }

    public function testAProofThatDoesNotReadIsOneFault(): void
    {
        self::assertSame(['The proof does not read: A proof is a JSON object.'], new RaffleVerifier()->problemsOfJson('{'));
    }

    public function testAPrizeDrawnFromTheOrganisersSeedIsAFault(): void
    {
        $proof = $this->change(new Draws()->noStake(), ['stake' => 'prize']);

        self::assertSame(['A raffle with a prize draws from a beacon round, not from a seed of its organiser.'], new RaffleVerifier()->problems($proof));
        self::assertFalse(new RaffleVerifier()->holds($proof));
    }

    public function testEntrantsOutOfOrderOrTwiceAreAFault(): void
    {
        $proof = new Draws()->prize();

        foreach ([array_reverse($proof->entrants), [...$proof->entrants, 'member-6']] as $entrants) {
            $changed = $this->redraw($proof, $entrants);

            self::assertSame(['The entrants are not each listed once, in byte order.'], new RaffleVerifier()->problems($changed));
        }
    }

    public function testEntrantsAddedAfterTheClosingAreAFault(): void
    {
        $proof = new Draws()->prize();
        $entrants = [...$proof->entrants, 'member-7'];
        $seed = $proof->seed;
        $changed = $this->change($proof, ['entrants' => $entrants, 'drawn' => Selection::draw($seed, $entrants, 3)]);

        self::assertSame(['The entrants do not hash to the hash announced when the entries closed.'], new RaffleVerifier()->problems($changed));
    }

    public function testARoundPublishedBeforeTheClosingIsAFault(): void
    {
        $proof = new Draws()->prize();
        $source = $proof->toArray()['source'];
        \assert(\is_array($source));
        $early = FakeBeacon::round(9_096_001);
        $source['beacon'] = ['round' => 9_096_001, 'randomness' => $early->randomness, 'signature' => $early->signature, 'previous_signature' => null, 'verification' => 'relay-quorum'];
        $seed = Entrants::seed($proof->raffle, $proof->entrantsHash, $early->randomness);
        $changed = $this->change($proof, ['source' => $source, 'seed' => $seed, 'drawn' => Selection::draw($seed, $proof->entrants, 3)]);

        self::assertSame(['The beacon round was published before the entries closed.'], new RaffleVerifier()->problems($changed));

        // Round 9 096 002 is the first published after the closing at 20:00:00.
        $first = FakeBeacon::round(9_096_002);
        $source['beacon'] = ['round' => 9_096_002, 'randomness' => $first->randomness, 'signature' => $first->signature, 'previous_signature' => null, 'verification' => 'relay-quorum'];
        $seed = Entrants::seed($proof->raffle, $proof->entrantsHash, $first->randomness);

        self::assertSame([], new RaffleVerifier()->problems($this->change($proof, ['source' => $source, 'seed' => $seed, 'drawn' => Selection::draw($seed, $proof->entrants, 3)])));
    }

    public function testASignatureVerifierChecksTheRound(): void
    {
        $proof = new Draws()->prize();
        $source = $proof->toArray()['source'];
        \assert(\is_array($source) && \is_array($source['chain']));

        self::assertSame([], new RaffleVerifier(new RecordingVerifier(true))->problems($proof));
        self::assertSame(['The signature of the beacon round does not verify against the public key of its chain.'], new RaffleVerifier(new RecordingVerifier(false))->problems($proof));

        $source['chain']['public_key'] = null;
        $keyless = $this->change($proof, ['source' => $source]);

        self::assertSame(['The signature of the beacon round does not verify against the public key of its chain.'], new RaffleVerifier(new RecordingVerifier(true))->problems($keyless));
        self::assertSame([], new RaffleVerifier()->problems($keyless));
    }

    public function testASeedThatDoesNotMatchItsCommitmentIsAFault(): void
    {
        $proof = new Draws()->noStake();
        $source = $proof->toArray()['source'];
        \assert(\is_array($source));
        $source['seed'] = str_repeat('1', 64);
        $seed = Entrants::seed($proof->raffle, $proof->entrantsHash, $source['seed']);
        $changed = $this->change($proof, ['source' => $source, 'seed' => $seed, 'drawn' => Selection::draw($seed, $proof->entrants, 3)]);

        self::assertSame(['The seed revealed does not match the commitment published when the raffle opened.'], new RaffleVerifier()->problems($changed));
    }

    public function testASeedOfTheDrawThatIsNotTheHashOfItsInputsIsAFault(): void
    {
        $proof = new Draws()->prize();
        $seed = str_repeat('2', 64);
        $changed = $this->change($proof, ['seed' => $seed, 'drawn' => Selection::draw($seed, $proof->entrants, 3)]);

        self::assertSame(['The seed of the draw is not the hash of the raffle, its entrants and its randomness.'], new RaffleVerifier()->problems($changed));
    }

    public function testNamesThatAreNotThoseOfTheSeedAreAFault(): void
    {
        $proof = new Draws()->prize();

        foreach ([array_reverse($proof->drawn), \array_slice($proof->drawn, 0, 2), [...$proof->drawn, 'member-9']] as $drawn) {
            self::assertSame(['The names drawn are not those the seed draws from the entrants.'], new RaffleVerifier()->problems($this->change($proof, ['drawn' => $drawn])));
        }
    }

    public function testABeaconDrawWithoutItsChainOrRoundIsAFault(): void
    {
        $proof = new Draws()->prize();
        $changed = new RaffleProof($proof->raffle, $proof->stake, $proof->winners, $proof->alternates, $proof->entrants, $proof->entrantsHash, $proof->source, null, null, null, $proof->beacon, $proof->seed, $proof->drawn, $proof->sealedAt, $proof->drawnAt);

        self::assertSame([
            'A raffle drawn from a beacon names the chain and the round it drew from.',
            'The seed of the draw is not the hash of the raffle, its entrants and its randomness.',
        ], new RaffleVerifier()->problems($changed));

        $changed = new RaffleProof($proof->raffle, $proof->stake, $proof->winners, $proof->alternates, $proof->entrants, $proof->entrantsHash, $proof->source, null, null, $proof->chain, null, $proof->seed, $proof->drawn, $proof->sealedAt, $proof->drawnAt);

        self::assertContains('A raffle drawn from a beacon names the chain and the round it drew from.', new RaffleVerifier()->problems($changed));
    }

    /**
     * The proof with these fields changed, its entrants hash kept.
     *
     * @param array<string, mixed> $fields
     */
    private function change(RaffleProof $proof, array $fields): RaffleProof
    {
        return RaffleProof::fromArray([...$proof->toArray(), ...$fields]);
    }

    /**
     * The proof with these entrants, hashed and drawn again, so that only their order or repetition is at fault.
     *
     * @param list<string> $entrants
     */
    private function redraw(RaffleProof $proof, array $entrants): RaffleProof
    {
        $hash = Entrants::hash($proof->raffle, $entrants);
        $beacon = $proof->beacon;
        $this->assertInstanceOf(Beacon::class, $beacon);
        $seed = Entrants::seed($proof->raffle, $hash, $beacon->randomness);

        return $this->change($proof, ['entrants' => $entrants, 'entrants_hash' => $hash, 'seed' => $seed, 'drawn' => Selection::draw($seed, $entrants, 3)]);
    }

    public function testACommitRevealProofWithoutItsSeedIsAFault(): void
    {
        $proof = new Draws()->noStake();
        $changed = new RaffleProof($proof->raffle, $proof->stake, $proof->winners, $proof->alternates, $proof->entrants, $proof->entrantsHash, $proof->source, null, null, null, null, $proof->seed, $proof->drawn, $proof->sealedAt, $proof->drawnAt);

        self::assertContains('The seed revealed does not match the commitment published when the raffle opened.', new RaffleVerifier()->problems($changed));
    }
}
