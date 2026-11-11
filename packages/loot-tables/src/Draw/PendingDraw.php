<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Draw;

use Random\Randomizer;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Fairness\Commitment;
use Tilttek\Dice\Fairness\Seed;
use Tilttek\LootTables\Exception\InvalidLoot;

/**
 * A draw committed and not yet revealed: a secret seed whose commitment is published first, and the number of the roll
 * under that seed.
 *
 * The seed stays with whoever draws until the draw is revealed; the commitment and the nonce are shown at once, before
 * the payment. The client seed is not part of it: the buyer gives it after seeing the commitment, and `LootDrawer`
 * mixes it in at the reveal, so that whoever drew the seed could not know the result when committing to it.
 */
final readonly class PendingDraw
{
    /** The longest client seed, in bytes. */
    public const int MAX_CLIENT_SEED = 256;

    private function __construct(
        public Seed $seed,
        public int $nonce,
    ) {
    }

    /**
     * A new draw, its seed drawn from a secure source of randomness.
     *
     * @throws InvalidLoot
     */
    public static function open(Randomizer $secure, int $nonce = 0): self
    {
        return self::checked(Seed::generate($secure), $nonce);
    }

    /**
     * A draw committed earlier, from its stored seed.
     *
     * @throws InvalidLoot
     */
    public static function restore(string $seed, int $nonce = 0): self
    {
        try {
            return self::checked(Seed::fromHex($seed), $nonce);
        } catch (InvalidDefinition $invalidDefinition) {
            throw InvalidLoot::because($invalidDefinition->getMessage());
        }
    }

    /**
     * The client seed a reveal mixes in, checked: 1 to 256 bytes of UTF-8 text, so that the proof writes out as JSON.
     *
     * @throws InvalidLoot
     */
    public static function clientSeed(string $clientSeed): string
    {
        if ('' === $clientSeed || \strlen($clientSeed) > self::MAX_CLIENT_SEED || 1 !== preg_match('//u', $clientSeed)) {
            throw InvalidLoot::because(\sprintf('A client seed is 1 to %d bytes of UTF-8 text.', self::MAX_CLIENT_SEED));
        }

        return $clientSeed;
    }

    /**
     * The SHA-256 of the seed: published before the draw.
     */
    public function commitment(): Commitment
    {
        return $this->seed->commitment();
    }

    private static function checked(Seed $seed, int $nonce): self
    {
        if ($nonce < 0) {
            throw InvalidLoot::because(\sprintf('The nonce of a draw is 0 or more; %d is not.', $nonce));
        }

        return new self($seed, $nonce);
    }
}
