<?php

declare(strict_types=1);

namespace Tilttek\Dice\Fairness;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * The commitment to a seed for one roll, as 64 hexadecimal digits: published before the roll, it binds the roller to a
 * seed it reveals only after, and to the nonce of that roll.
 *
 * It is the SHA-256 of `FORMAT`, a zero byte, the 32 bytes of the seed and the nonce as 8 big-endian bytes, two's
 * complement. A commitment computed otherwise — the SHA-256 of the seed alone, say — matches no seed.
 */
final readonly class Commitment
{
    /** The domain of the commitments, with the version of their format. */
    public const string FORMAT = 'tilttek/dice commitment v2';

    private function __construct(
        public string $hex,
    ) {
    }

    public static function fromHex(string $hex): self
    {
        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $hex)) {
            throw InvalidDefinition::because('A commitment is 64 lower-case hexadecimal digits.');
        }

        return new self($hex);
    }

    /**
     * Whether this is the commitment to the seed for the roll of this nonce, compared in constant time.
     */
    public function matches(Seed $seed, int $nonce = 0): bool
    {
        return hash_equals($this->hex, $seed->commitment($nonce)->hex);
    }
}
