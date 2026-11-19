<?php

declare(strict_types=1);

namespace Tilttek\Raffle;

use DateTimeImmutable;
use Tilttek\Raffle\Beacon\Beacon;
use Tilttek\Raffle\Beacon\BeaconChain;
use Tilttek\Raffle\Beacon\BeaconVerification;
use Tilttek\Raffle\Exception\InvalidRaffle;

/**
 * A raffle as the store keeps it: what it puts at stake, where its seed comes from, how many winners and alternates it
 * draws, and how far it has gone — open, sealed with the hash of its entrants and its source of randomness fixed, or
 * drawn.
 *
 * Every entrant holds one chance: the raffle counts entrants, never tickets, and nothing in it buys or weighs a
 * chance. A raffle seeded by its organiser keeps the seed here, secret until the draw: whoever reads it knows the
 * winners as soon as the entries close.
 */
final readonly class Raffle
{
    /** The most winners a raffle draws. */
    public const int MAX_WINNERS = 1000;

    /** The most alternates a raffle draws after its winners. */
    public const int MAX_ALTERNATES = 1000;

    /** The most entrants a raffle takes. */
    public const int MAX_ENTRANTS = 100_000;

    private function __construct(
        public string $id,
        public Stake $stake,
        public SeedSource $source,
        public int $winners,
        public int $alternates,
        public RaffleStatus $status,
        public int $version,
        public DateTimeImmutable $openedAt,
        public int $entrantCount,
        public ?string $commitment,
        public ?string $seed,
        public ?DateTimeImmutable $sealedAt,
        public ?string $entrantsHash,
        public ?BeaconChain $chain,
        public ?int $round,
        public ?Beacon $beacon,
        public ?DateTimeImmutable $drawnAt,
    ) {
    }

    /**
     * A newly opened raffle, at version 1.
     *
     * @param ?string $seed the organiser's secret seed, 64 hexadecimal digits, for a raffle seeded by commit-reveal
     *
     * @throws InvalidRaffle when a value is out of its range, or the seed does not match the source
     */
    public static function open(string $id, Stake $stake, SeedSource $source, int $winners, int $alternates, DateTimeImmutable $at, ?string $seed = null): self
    {
        self::checkCounts($winners, $alternates);

        if ((SeedSource::CommitReveal === $source) !== (null !== $seed)) {
            throw InvalidRaffle::because('A raffle seeded by commit-reveal holds its seed, and only it does.');
        }

        $commitment = null === $seed ? null : self::commit(Encoding::hex($seed, 'seed'));

        return new self(Encoding::id($id), $stake, $source, $winners, $alternates, RaffleStatus::Open, 1, Instant::utc($at), 0, $commitment, $seed, null, null, null, null, null, null);
    }

    /**
     * @throws InvalidRaffle when the winners are not 1 to 1 000 or the alternates 0 to 1 000
     *
     * @internal
     */
    public static function checkCounts(int $winners, int $alternates): void
    {
        if ($winners < 1 || $winners > self::MAX_WINNERS || $alternates < 0 || $alternates > self::MAX_ALTERNATES) {
            throw InvalidRaffle::because(\sprintf('A raffle draws 1 to %d winners and 0 to %d alternates.', self::MAX_WINNERS, self::MAX_ALTERNATES));
        }
    }

    /**
     * The commitment to a seed: the SHA-256 of its 64 hexadecimal digits.
     */
    public static function commit(string $seed): string
    {
        return hash('sha256', $seed);
    }

    /**
     * A raffle as `toArray()` wrote it, for a store.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidRaffle when the data does not read as a raffle
     */
    public static function fromArray(array $data): self
    {
        $stake = \is_string($data['stake'] ?? null) ? Stake::tryFrom($data['stake']) : null;
        $source = \is_string($data['source'] ?? null) ? SeedSource::tryFrom($data['source']) : null;
        $status = \is_string($data['status'] ?? null) ? RaffleStatus::tryFrom($data['status']) : null;
        $id = $data['id'] ?? null;
        $winners = $data['winners'] ?? null;
        $alternates = $data['alternates'] ?? null;
        $version = $data['version'] ?? null;
        $openedAt = $data['opened_at'] ?? null;
        $count = $data['entrant_count'] ?? null;
        $seed = $data['seed'] ?? null;

        if (!$stake instanceof Stake || !$source instanceof SeedSource || !$status instanceof RaffleStatus || !\is_string($id) || !\is_int($winners) || !\is_int($alternates)
            || !\is_int($version) || !\is_string($openedAt) || !\is_int($count) || (null !== $seed && !\is_string($seed))) {
            throw InvalidRaffle::because('A raffle holds its identifier, stake, source, status, winners, alternates, version, opening time, number of entrants and seed.');
        }

        if ($version < 1 || $count < 0 || $count > self::MAX_ENTRANTS) {
            throw InvalidRaffle::because(\sprintf('A raffle is at version 1 or more and holds 0 to %d entrants.', self::MAX_ENTRANTS));
        }

        $raffle = self::open($id, $stake, $source, $winners, $alternates, Instant::read($openedAt), $seed);

        if (RaffleStatus::Open === $status) {
            return new self($raffle->id, $stake, $source, $winners, $alternates, $status, $version, $raffle->openedAt, $count, $raffle->commitment, $seed, null, null, null, null, null, null);
        }

        $sealedAt = $data['sealed_at'] ?? null;
        $hash = $data['entrants_hash'] ?? null;

        if (!\is_string($sealedAt) || !\is_string($hash) || $count < 1) {
            throw InvalidRaffle::because('A sealed raffle holds the time it was sealed, the hash of its entrants and at least one entrant.');
        }

        [$chain, $round] = SeedSource::Beacon === $source ? self::readChain($data) : [null, null];
        $beacon = RaffleStatus::Drawn === $status && SeedSource::Beacon === $source ? self::beaconOf($data['beacon'] ?? null) : null;
        $drawnAt = $data['drawn_at'] ?? null;

        if ((RaffleStatus::Drawn === $status) !== \is_string($drawnAt)) {
            throw InvalidRaffle::because('A drawn raffle holds the time it was drawn, and only it does.');
        }

        return new self($raffle->id, $stake, $source, $winners, $alternates, $status, $version, $raffle->openedAt, $count, $raffle->commitment, $seed, Instant::read($sealedAt), Encoding::hex($hash, 'entrants hash'), $chain, $round, $beacon, \is_string($drawnAt) ? Instant::read($drawnAt) : null);
    }

    /**
     * The next version, with one more entrant.
     */
    public function entered(): self
    {
        return new self($this->id, $this->stake, $this->source, $this->winners, $this->alternates, $this->status, $this->version + 1, $this->openedAt, $this->entrantCount + 1, $this->commitment, $this->seed, null, null, null, null, null, null);
    }

    /**
     * The next version, its entries closed and hashed, and the beacon round it draws from for a raffle seeded by a
     * beacon.
     */
    public function sealed(string $entrantsHash, DateTimeImmutable $at, ?BeaconChain $chain = null, ?int $round = null): self
    {
        return new self($this->id, $this->stake, $this->source, $this->winners, $this->alternates, RaffleStatus::Sealed, $this->version + 1, $this->openedAt, $this->entrantCount, $this->commitment, $this->seed, Instant::utc($at), $entrantsHash, $chain, $round, null, null);
    }

    /**
     * The next version, drawn, with the beacon round it drew from for a raffle seeded by a beacon.
     */
    public function drawn(DateTimeImmutable $at, ?Beacon $beacon = null): self
    {
        return new self($this->id, $this->stake, $this->source, $this->winners, $this->alternates, RaffleStatus::Drawn, $this->version + 1, $this->openedAt, $this->entrantCount, $this->commitment, $this->seed, $this->sealedAt, $this->entrantsHash, $this->chain, $this->round, $beacon, Instant::utc($at));
    }

    /**
     * How many names the draw gives: the winners and the alternates, or every entrant when there are fewer.
     */
    public function draws(): int
    {
        return min($this->winners + $this->alternates, $this->entrantCount);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'stake' => $this->stake->value,
            'source' => $this->source->value,
            'winners' => $this->winners,
            'alternates' => $this->alternates,
            'status' => $this->status->value,
            'version' => $this->version,
            'opened_at' => Instant::write($this->openedAt),
            'entrant_count' => $this->entrantCount,
            'seed' => $this->seed,
            'sealed_at' => $this->sealedAt instanceof DateTimeImmutable ? Instant::write($this->sealedAt) : null,
            'entrants_hash' => $this->entrantsHash,
            'chain' => $this->chain instanceof BeaconChain ? ['hash' => $this->chain->hash, 'genesis' => $this->chain->genesis, 'period' => $this->chain->period, 'scheme' => $this->chain->scheme, 'public_key' => $this->chain->publicKey] : null,
            'round' => $this->round,
            'beacon' => $this->beacon instanceof Beacon ? ['round' => $this->beacon->round, 'randomness' => $this->beacon->randomness, 'signature' => $this->beacon->signature, 'previous_signature' => $this->beacon->previousSignature, 'verification' => $this->beacon->verification->value] : null,
            'drawn_at' => $this->drawnAt instanceof DateTimeImmutable ? Instant::write($this->drawnAt) : null,
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array{0: BeaconChain, 1: int}
     */
    private static function readChain(array $data): array
    {
        $chain = $data['chain'] ?? null;
        $round = $data['round'] ?? null;

        if (!\is_array($chain) || !\is_int($round)) {
            throw InvalidRaffle::because('A sealed raffle seeded by a beacon holds the chain and the round it draws from.');
        }

        return [self::chainOf($chain), BeaconChain::round($round)];
    }

    /**
     * A beacon chain as published in a raffle or a proof.
     *
     * @param array<array-key, mixed> $chain
     *
     * @throws InvalidRaffle
     *
     * @internal
     */
    public static function chainOf(array $chain): BeaconChain
    {
        ['hash' => $hash, 'genesis' => $genesis, 'period' => $period, 'scheme' => $scheme] = $chain + ['hash' => null, 'genesis' => null, 'period' => null, 'scheme' => null];
        $key = $chain['public_key'] ?? null;

        if (!\is_string($hash) || !\is_int($genesis) || !\is_int($period) || !\is_string($scheme) || (null !== $key && !\is_string($key))) {
            throw InvalidRaffle::because('A beacon chain holds its hash, genesis, period, scheme and public key.');
        }

        return new BeaconChain($hash, $genesis, $period, $scheme, $key);
    }

    /**
     * A beacon round as published in a raffle or a proof.
     *
     * @throws InvalidRaffle
     *
     * @internal
     */
    public static function beaconOf(mixed $beacon): Beacon
    {
        if (!\is_array($beacon)) {
            throw InvalidRaffle::because('A raffle drawn from a beacon holds the round it drew from.');
        }

        ['round' => $round, 'randomness' => $randomness, 'signature' => $signature] = $beacon + ['round' => null, 'randomness' => null, 'signature' => null];
        $previous = $beacon['previous_signature'] ?? null;
        $verification = \is_string($beacon['verification'] ?? null) ? BeaconVerification::tryFrom($beacon['verification']) : null;

        if (!\is_int($round) || !\is_string($randomness) || !\is_string($signature) || (null !== $previous && !\is_string($previous)) || !$verification instanceof BeaconVerification) {
            throw InvalidRaffle::because('A beacon round holds its number, randomness, signature, previous signature and verification.');
        }

        return new Beacon($round, $randomness, $signature, $previous, $verification);
    }
}
