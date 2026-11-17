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
 * same seed, client seed and nonce always give the same dice, and none of them can be guessed from the others. The
 * commitment published before a roll binds both the seed and the nonce of that roll, in the format `Commitment::FORMAT`
 * names.
 */
final readonly class Seed
{
    public const int BYTES = 32;

    /** The longest client seed, in bytes: a seed from a public request costs a bounded HMAC. */
    public const int MAX_CLIENT_SEED = 256;

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
     * The commitment to this seed for one roll, published before the roll: the SHA-256 of `Commitment::FORMAT`, a zero
     * byte, the 32 bytes of the seed and the nonce as 8 big-endian bytes. It holds for that nonce only.
     *
     * @param int $nonce the number of the roll under this seed, the one `randomizer()` is given
     */
    public function commitment(int $nonce = 0): Commitment
    {
        return Commitment::fromHex(hash('sha256', Commitment::FORMAT."\0".$this->bytes.pack('J', $nonce)));
    }

    /**
     * The randomness of one roll: this seed, the seed the player chose and the number of the roll.
     *
     * @throws InvalidDefinition when the client seed is longer than `MAX_CLIENT_SEED` bytes
     */
    public function randomizer(string $clientSeed = '', int $nonce = 0): Randomizer
    {
        if (\strlen($clientSeed) > self::MAX_CLIENT_SEED) {
            throw InvalidDefinition::because(\sprintf('A client seed is at most %d bytes.', self::MAX_CLIENT_SEED));
        }

        return new Randomizer(new Xoshiro256StarStar(hash_hmac('sha256', $clientSeed."\n".$nonce, $this->bytes, true)));
    }
}
