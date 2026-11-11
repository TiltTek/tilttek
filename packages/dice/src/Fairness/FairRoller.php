<?php

declare(strict_types=1);

namespace Tilttek\Dice\Fairness;

use RoundingMode;
use Tilttek\Dice\Exception\CannotRoll;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Rolling\Roller;

/**
 * Verifiable rolls, by commitment: the roller draws a seed and publishes its commitment; the player sends the
 * expression and a seed of their own; the roller rolls, then reveals the seed; anyone checks that the seed matches the
 * commitment and that rolling again gives the same dice. The roller could not choose the result: its seed was fixed
 * before the player's was known.
 */
final readonly class FairRoller
{
    public function __construct(
        private Roller $roller = new Roller(),
    ) {
    }

    /**
     * @param Seed   $seed       drawn before the roll, its commitment already published
     * @param string $clientSeed chosen by the player after the commitment was published
     * @param int    $nonce      the number of this roll under the same seed
     *
     * @throws LimitExceeded
     * @throws CannotRoll
     */
    public function roll(Pool $pool, Seed $seed, string $clientSeed = '', int $nonce = 0, RoundingMode $rounding = RoundingMode::NegativeInfinity): VerifiableRoll
    {
        return new VerifiableRoll($seed->commitment(), $seed, $clientSeed, $nonce, $this->roller->roll($pool, $seed->randomizer($clientSeed, $nonce), $rounding));
    }

    /**
     * Whether a published roll is honest: the seed matches the commitment, and rolling the pool again with it, and with
     * the rounding it was rolled with, gives the same result as the one published.
     *
     * @param list<array<string, mixed>> $claimed the published result, as `PoolResult::toArray()` wrote it
     */
    public function verify(Commitment $commitment, Seed $seed, string $clientSeed, int $nonce, Pool $pool, array $claimed, RoundingMode $rounding = RoundingMode::NegativeInfinity): bool
    {
        return $commitment->matches($seed) && $this->roller->roll($pool, $seed->randomizer($clientSeed, $nonce), $rounding)->toArray() === $claimed;
    }
}
