<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Beacon;

use DateTimeImmutable;
use Tilttek\Raffle\Encoding;
use Tilttek\Raffle\Exception\InvalidRaffle;

/**
 * A chain of a public randomness beacon: its hash, the time of its first round, the seconds between two rounds, its
 * signature scheme, and its public key once read from the beacon. Round `r` is published at
 * `genesis + (r - 1) × period`.
 */
final readonly class BeaconChain
{
    /** The highest round the library reads, so that the time of a round stays a whole number of seconds. */
    public const int MAX_ROUND = 1 << 40;

    /**
     * @param string  $hash      the chain's hash, 64 hexadecimal digits
     * @param int     $genesis   the time of round 1, in seconds since the Unix epoch
     * @param int     $period    the seconds between two rounds, 1 to 3 600
     * @param string  $scheme    the name of the signature scheme
     * @param ?string $publicKey the public key the signatures verify against, in hexadecimal, once read
     *
     * @throws InvalidRaffle when a value is out of its range
     */
    public function __construct(
        public string $hash,
        public int $genesis,
        public int $period,
        public string $scheme,
        public ?string $publicKey = null,
    ) {
        Encoding::hex($hash, 'chain hash');

        if ($genesis < 1 || $period < 1 || $period > 3600) {
            throw InvalidRaffle::because('A beacon chain starts after the Unix epoch and publishes a round every 1 to 3600 seconds.');
        }

        if (1 !== preg_match('/^[a-z0-9-]{1,64}$/D', $scheme)) {
            throw InvalidRaffle::because('The scheme of a beacon chain is 1 to 64 lower-case letters, digits or hyphens.');
        }

        if (null !== $publicKey && 1 !== preg_match('/^[0-9a-f]{2,512}$/D', $publicKey)) {
            throw InvalidRaffle::because('The public key of a beacon chain is 2 to 512 lower-case hexadecimal digits.');
        }
    }

    /**
     * The same chain with the public key read from the beacon.
     */
    public function withPublicKey(string $publicKey): self
    {
        return new self($this->hash, $this->genesis, $this->period, $this->scheme, $publicKey);
    }

    /**
     * Whether the other chain is this one: the same hash, genesis, period and scheme.
     */
    public function isSame(self $other): bool
    {
        return $this->hash === $other->hash && $this->genesis === $other->genesis && $this->period === $other->period && $this->scheme === $other->scheme;
    }

    /**
     * The time round `$round` is published.
     */
    public function timeOf(int $round): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.($this->genesis + (self::round($round) - 1) * $this->period));
    }

    /**
     * The first round published strictly after `$at`: a round nobody knows at that time.
     */
    public function firstRoundAfter(DateTimeImmutable $at): int
    {
        $elapsed = $at->getTimestamp() - $this->genesis;

        return $elapsed < 0 ? 1 : intdiv($elapsed, $this->period) + 2;
    }

    /**
     * @throws InvalidRaffle when the round is not 1 to 2⁴⁰
     */
    public static function round(int $round): int
    {
        if ($round < 1 || $round > self::MAX_ROUND) {
            throw InvalidRaffle::because(\sprintf('A beacon round is 1 to %d.', self::MAX_ROUND));
        }

        return $round;
    }
}
