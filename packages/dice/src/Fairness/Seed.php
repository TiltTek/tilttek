<?php

declare(strict_types=1);

namespace Tilttek\Dice\Fairness;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * The 32 bytes a verifiable or replayable roll draws its dice from, written as 64 hexadecimal digits.
 *
 * The dice come from `Xoshiro256**` seeded with an HMAC-SHA256 of the client's seed and the nonce under this seed: the
 * same seed, client seed and nonce always give the same dice, and none of them can be guessed from the others.
 */
final readonly class Seed
{
    public const int BYTES = 32;

    private function __construct(
        private string $bytes,
    ) {
    }

    /**
     * A new seed, drawn from a secure source of randomness.
     */
    public static function generate(Randomizer $secure): self
    {
        return new self($secure->getBytes(self::BYTES));
    }

    public static function fromHex(string $hex): self
    {
        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $hex)) {
            throw InvalidDefinition::because('A seed is 64 lower-case hexadecimal digits.');
        }

        return new self(pack('H*', $hex));
    }

    public function hex(): string
    {
        return sodium_bin2hex($this->bytes);
    }

    /**
     * The SHA-256 of the seed, published before the roll.
     */
    public function commitment(): Commitment
    {
        return Commitment::fromHex(hash('sha256', $this->bytes));
    }

    /**
     * The randomness of one roll: this seed, the seed the player chose and the number of the roll.
     */
    public function randomizer(string $clientSeed = '', int $nonce = 0): Randomizer
    {
        return new Randomizer(new Xoshiro256StarStar(hash_hmac('sha256', $clientSeed."\n".$nonce, $this->bytes, true)));
    }
}
