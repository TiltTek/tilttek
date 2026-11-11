<?php

declare(strict_types=1);

namespace Tilttek\Dice\Fairness;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * The SHA-256 of a seed, as 64 hexadecimal digits: published before a roll, it binds the roller to a seed it reveals
 * only after.
 */
final readonly class Commitment
{
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

    public function matches(Seed $seed): bool
    {
        return hash_equals($this->hex, $seed->commitment()->hex);
    }
}
