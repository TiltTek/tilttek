<?php

declare(strict_types=1);

namespace Tilttek\Raffle;

use Tilttek\Raffle\Beacon\Beacon;
use Tilttek\Raffle\Beacon\BeaconChain;
use Tilttek\Raffle\Contract\BeaconSignatureVerifier;
use Tilttek\Raffle\Exception\InvalidRaffle;

/**
 * Checks a published proof, as any player can: the raffle drew from the source its stake demands, its entrants are
 * each listed once and hash to the hash announced, its randomness is the beacon round published after the entries
 * closed or the seed the organiser committed to, and drawing again from the seed gives the same names in the same
 * order. Given a signature verifier, it also verifies the beacon round's signature.
 */
final readonly class RaffleVerifier
{
    public function __construct(
        private ?BeaconSignatureVerifier $signatures = null,
    ) {
    }

    /**
     * What breaks the proof, one sentence per fault; none for a proof that holds.
     *
     * @return list<string>
     */
    public function problems(RaffleProof $proof): array
    {
        $problems = [];

        if (Stake::Prize === $proof->stake && SeedSource::Beacon !== $proof->source) {
            $problems[] = 'A raffle with a prize draws from a beacon round, not from a seed of its organiser.';
        }

        if (Entrants::canonical($proof->entrants) !== $proof->entrants) {
            $problems[] = 'The entrants are not each listed once, in byte order.';
        }

        if (!hash_equals(Entrants::hash($proof->raffle, $proof->entrants), $proof->entrantsHash)) {
            $problems[] = 'The entrants do not hash to the hash announced when the entries closed.';
        }

        $randomness = SeedSource::Beacon === $proof->source ? $this->beaconProblems($proof, $problems) : $this->revealProblems($proof, $problems);

        if (!hash_equals(Entrants::seed($proof->raffle, $proof->entrantsHash, $randomness), $proof->seed)) {
            $problems[] = 'The seed of the draw is not the hash of the raffle, its entrants and its randomness.';
        }

        if (Selection::draw($proof->seed, $proof->entrants, min($proof->winners + $proof->alternates, \count($proof->entrants))) !== $proof->drawn) {
            $problems[] = 'The names drawn are not those the seed draws from the entrants.';
        }

        return $problems;
    }

    public function holds(RaffleProof $proof): bool
    {
        return [] === $this->problems($proof);
    }

    /**
     * The faults of a proof as published; a proof that does not read is one fault.
     *
     * @return list<string>
     */
    public function problemsOfJson(string $json): array
    {
        try {
            return $this->problems(RaffleProof::fromJson($json));
        } catch (InvalidRaffle $invalidRaffle) {
            return ['The proof does not read: '.$invalidRaffle->getMessage()];
        }
    }

    /**
     * @param list<string> $problems
     *
     * @param-out list<string> $problems
     */
    private function beaconProblems(RaffleProof $proof, array &$problems): string
    {
        $chain = $proof->chain;
        $beacon = $proof->beacon;

        if (!$chain instanceof BeaconChain || !$beacon instanceof Beacon) {
            $problems[] = 'A raffle drawn from a beacon names the chain and the round it drew from.';

            return '';
        }

        if ($beacon->round < $chain->firstRoundAfter($proof->sealedAt)) {
            $problems[] = 'The beacon round was published before the entries closed.';
        }

        if ($this->signatures instanceof BeaconSignatureVerifier && (null === $chain->publicKey || !$this->signatures->verify($chain, $beacon))) {
            $problems[] = 'The signature of the beacon round does not verify against the public key of its chain.';
        }

        return $beacon->randomness;
    }

    /**
     * @param list<string> $problems
     *
     * @param-out list<string> $problems
     */
    private function revealProblems(RaffleProof $proof, array &$problems): string
    {
        $seed = (string) $proof->revealedSeed;

        if (!hash_equals((string) $proof->commitment, Raffle::commit($seed))) {
            $problems[] = 'The seed revealed does not match the commitment published when the raffle opened.';
        }

        return $seed;
    }
}
