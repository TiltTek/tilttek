<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls;

use Tilttek\SecretRolls\Exception\InvalidSecretRoll;

/**
 * What the table sees of a hidden roll the moment it is made: the SHA-256 of its opening — its seed, its expression,
 * its result and its context —, as 64 hexadecimal digits. The 32 secret bytes of the seed keep it from telling anything
 * of the result; once the opening is revealed, anyone computes the hash again and compares.
 */
final readonly class RollCommitment
{
    private function __construct(
        public string $hex,
    ) {
    }

    /**
     * @throws InvalidSecretRoll when the value is not 64 lower-case hexadecimal digits
     */
    public static function fromHex(string $hex): self
    {
        return new self(Encoding::hex64($hex, 'commitment'));
    }

    public static function of(RollOpening $opening): self
    {
        return new self(Encoding::hash([Encoding::VERSION, ...array_values($opening->toArray())]));
    }

    /**
     * Whether the two commitments are the same, compared in constant time.
     */
    public function equals(self $other): bool
    {
        return hash_equals($this->hex, $other->hex);
    }
}
