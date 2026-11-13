<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Generator;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Heraldry\Exception\InvalidSeed;

/**
 * The 32 bytes a coat of arms is drawn from. The server draws them from the system's secure source; written as 64
 * hexadecimal digits, they draw the same arms again, on any server.
 */
final readonly class Seed
{
    /** The length of a seed, in bytes. */
    public const int BYTES = 32;

    private function __construct(
        public string $bytes,
    ) {
    }

    /** A new seed from the system's secure source of randomness. */
    public static function generate(): self
    {
        return new self(random_bytes(self::BYTES));
    }

    /**
     * @throws InvalidSeed when the text is not 64 hexadecimal digits
     */
    public static function fromHex(string $hex): self
    {
        if (1 !== preg_match('/\A[0-9a-fA-F]{64}\z/', $hex)) {
            throw InvalidSeed::notHex();
        }

        return new self(sodium_hex2bin($hex));
    }

    /** The seed as 64 lowercase hexadecimal digits. */
    public function hex(): string
    {
        return sodium_bin2hex($this->bytes);
    }

    /**
     * A randomizer that gives the same sequence for the same seed, everywhere: Xoshiro256** keyed with the SHA-256 of
     * the seed, so that any seed — 32 zero bytes included — keys it.
     */
    public function randomizer(): Randomizer
    {
        return new Randomizer(new Xoshiro256StarStar(hash('sha256', $this->bytes, true)));
    }
}
