<?php

declare(strict_types=1);

namespace Tilttek\Raffle;

use DateTimeImmutable;
use JsonException;
use Tilttek\Raffle\Beacon\Beacon;
use Tilttek\Raffle\Beacon\BeaconChain;
use Tilttek\Raffle\Exception\InvalidRaffle;

/**
 * Everything a player needs to draw a raffle again on their own: the entrants and their hash, the source of the
 * randomness — the beacon round with its signature and its chain, or the organiser's commitment and seed —, the seed of
 * the draw, and the names drawn, the winners first, then the alternates in the order they replace a winner.
 */
final readonly class RaffleProof
{
    /** The longest proof `fromJson()` reads, in bytes. */
    public const int MAX_JSON = 16 * 1024 * 1024;

    /**
     * @param list<string> $entrants the entrants, in byte order
     * @param list<string> $drawn    the names drawn, the winners first
     */
    public function __construct(
        public string $raffle,
        public Stake $stake,
        public int $winners,
        public int $alternates,
        public array $entrants,
        public string $entrantsHash,
        public SeedSource $source,
        public ?string $commitment,
        public ?string $revealedSeed,
        public ?BeaconChain $chain,
        public ?Beacon $beacon,
        public string $seed,
        public array $drawn,
        public DateTimeImmutable $sealedAt,
        public DateTimeImmutable $drawnAt,
    ) {
    }

    /**
     * The winners, in the order drawn.
     *
     * @return list<string>
     */
    public function winnerList(): array
    {
        return \array_slice($this->drawn, 0, $this->winners);
    }

    /**
     * The alternates, in the order they replace a winner who does not claim the prize.
     *
     * @return list<string>
     */
    public function alternateList(): array
    {
        return \array_slice($this->drawn, $this->winners);
    }

    /**
     * The place of an entrant in the order drawn — 1 for the first prize of a podium, `winners + 1` for the first
     * alternate —, or null when they were not drawn.
     */
    public function place(string $entrant): ?int
    {
        $index = array_search($entrant, $this->drawn, true);

        return false === $index ? null : $index + 1;
    }

    /**
     * A proof as `toJson()` wrote it.
     *
     * @throws InvalidRaffle when the text is too long, is not JSON, or does not read as a proof
     */
    public static function fromJson(string $json): self
    {
        if (\strlen($json) > self::MAX_JSON) {
            throw InvalidRaffle::because(\sprintf('A proof is at most %d bytes of JSON.', self::MAX_JSON));
        }

        try {
            $data = json_decode($json, true, 8, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidRaffle::because('A proof is a JSON object.');
        }

        if (!\is_array($data)) {
            throw InvalidRaffle::because('A proof is a JSON object.');
        }

        return self::fromArray($data);
    }

    /**
     * A proof as `toArray()` wrote it, every field read; whether it holds is `RaffleVerifier`'s to tell.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidRaffle when the data does not read as a proof
     */
    public static function fromArray(array $data): self
    {
        $stake = \is_string($data['stake'] ?? null) ? Stake::tryFrom($data['stake']) : null;
        $source = $data['source'] ?? null;
        $kind = \is_array($source) && \is_string($source['kind'] ?? null) ? SeedSource::tryFrom($source['kind']) : null;
        ['version' => $version, 'raffle' => $raffle, 'winners' => $winners, 'alternates' => $alternates, 'entrants' => $entrants, 'entrants_hash' => $hash, 'seed' => $seed, 'drawn' => $drawn, 'sealed_at' => $sealedAt, 'drawn_at' => $drawnAt]
            = $data + ['version' => null, 'raffle' => null, 'winners' => null, 'alternates' => null, 'entrants' => null, 'entrants_hash' => null, 'seed' => null, 'drawn' => null, 'sealed_at' => null, 'drawn_at' => null];

        if (Encoding::VERSION !== $version || !$stake instanceof Stake || !$kind instanceof SeedSource || !\is_string($raffle) || !\is_int($winners) || !\is_int($alternates)
            || !\is_string($hash) || !\is_string($seed) || !\is_string($sealedAt) || !\is_string($drawnAt)) {
            throw InvalidRaffle::because(\sprintf('A proof of version %s holds its raffle, stake, winners, alternates, entrants, their hash, source, seed, names drawn and times.', Encoding::VERSION));
        }

        Raffle::checkCounts($winners, $alternates);

        return new self(
            Encoding::id($raffle),
            $stake,
            $winners,
            $alternates,
            self::names($entrants, Raffle::MAX_ENTRANTS, 'entrants'),
            Encoding::hex($hash, 'entrants hash'),
            $kind,
            SeedSource::CommitReveal === $kind ? Encoding::hex(self::text($source, 'commitment'), 'commitment') : null,
            SeedSource::CommitReveal === $kind ? Encoding::hex(self::text($source, 'seed'), 'seed') : null,
            SeedSource::Beacon === $kind ? Raffle::chainOf(\is_array($source['chain'] ?? null) ? $source['chain'] : []) : null,
            SeedSource::Beacon === $kind ? Raffle::beaconOf($source['beacon'] ?? null) : null,
            Encoding::hex($seed, 'seed of the draw'),
            self::names($drawn, Raffle::MAX_WINNERS + Raffle::MAX_ALTERNATES, 'names drawn'),
            Instant::read($sealedAt),
            Instant::read($drawnAt),
        );
    }

    /**
     * The proof as JSON: what the organiser publishes, and what a player downloads and checks.
     */
    public function toJson(): string
    {
        return Encoding::json($this->toArray());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $source = SeedSource::Beacon === $this->source
            ? ['kind' => $this->source->value, 'chain' => $this->chain instanceof BeaconChain ? ['hash' => $this->chain->hash, 'genesis' => $this->chain->genesis, 'period' => $this->chain->period, 'scheme' => $this->chain->scheme, 'public_key' => $this->chain->publicKey] : null,
                'beacon' => $this->beacon instanceof Beacon ? ['round' => $this->beacon->round, 'randomness' => $this->beacon->randomness, 'signature' => $this->beacon->signature, 'previous_signature' => $this->beacon->previousSignature, 'verification' => $this->beacon->verification->value] : null]
            : ['kind' => $this->source->value, 'commitment' => $this->commitment, 'seed' => $this->revealedSeed];

        return [
            'version' => Encoding::VERSION,
            'raffle' => $this->raffle,
            'stake' => $this->stake->value,
            'winners' => $this->winners,
            'alternates' => $this->alternates,
            'entrants' => $this->entrants,
            'entrants_hash' => $this->entrantsHash,
            'source' => $source,
            'seed' => $this->seed,
            'drawn' => $this->drawn,
            'sealed_at' => Instant::write($this->sealedAt),
            'drawn_at' => Instant::write($this->drawnAt),
        ];
    }

    /**
     * @param array<array-key, mixed> $source
     */
    private static function text(array $source, string $field): string
    {
        $value = $source[$field] ?? null;

        return \is_string($value) ? $value : throw InvalidRaffle::because(\sprintf('A source of commit-reveal holds its %s.', $field));
    }

    /**
     * @return list<string>
     */
    private static function names(mixed $names, int $max, string $what): array
    {
        if (!\is_array($names) || !array_is_list($names) || \count($names) > $max) {
            throw InvalidRaffle::because(\sprintf('The %s of a proof are a list of at most %d entrants.', $what, $max));
        }

        return array_map(static fn (mixed $name): string => \is_string($name) ? Encoding::entrant($name) : throw InvalidRaffle::because('An entrant is text.'), $names);
    }
}
