<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability;

use InvalidArgumentException;

/**
 * How far an exact computation goes, and how an estimate is drawn when it stops.
 *
 * An explosion is followed to `explosionDepth` explosions per die, and the probability of going further is stated as
 * what remains. An exact computation does at most `workLimit` operations on its weights; past that, and for the
 * constructs it does not follow, `samples` rolls drawn from `seed` give an estimate — the same seed gives the same
 * estimate. An estimate rolls at most `rollLimit` dice over all its samples, explosions and rerolls included; past
 * that, no probability is given. The work of an expression is bounded either way.
 */
final readonly class Settings
{
    /**
     * @throws InvalidArgumentException when the depth, the work limit, the number of samples or the roll limit is below 1
     */
    public function __construct(
        public int $explosionDepth = 10,
        public int $workLimit = 500_000,
        public int $samples = 20_000,
        public int $seed = 0,
        public int $rollLimit = 200_000,
    ) {
        foreach (['explosion depth' => $explosionDepth, 'work limit' => $workLimit, 'number of samples' => $samples, 'roll limit' => $rollLimit] as $name => $value) {
            if ($value < 1) {
                throw new InvalidArgumentException(\sprintf('The %s is at least 1; %d is not.', $name, $value));
            }
        }
    }
}
