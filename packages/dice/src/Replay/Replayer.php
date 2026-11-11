<?php

declare(strict_types=1);

namespace Tilttek\Dice\Replay;

use Random\Randomizer;
use RoundingMode;
use Tilttek\Dice\Exception\CannotRoll;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Fairness\Seed;
use Tilttek\Dice\Rolling\Roller;

/**
 * Replayable rolls: each draws an identifier and a seed from a secure source, and rolls from the seed, so that its
 * expression, its seed and its rounding give the same dice again, die for die.
 */
final readonly class Replayer
{
    public const string ID_PATTERN = '/^[0-9a-f]{32}$/D';

    public function __construct(
        private Roller $roller = new Roller(),
    ) {
    }

    /**
     * @throws LimitExceeded
     * @throws CannotRoll
     */
    public function roll(Pool $pool, Randomizer $secure, RoundingMode $rounding = RoundingMode::NegativeInfinity): ReplayableRoll
    {
        $id = sodium_bin2hex($secure->getBytes(16));
        $seed = Seed::generate($secure);

        return new ReplayableRoll($id, $seed, $this->roller->roll($pool, $seed->randomizer(), $rounding));
    }

    /**
     * The same roll, from its identifier, its expression, its seed and its rounding.
     *
     * @throws InvalidDefinition when the identifier is not one this class draws
     */
    public function replay(string $id, Pool $pool, Seed $seed, RoundingMode $rounding = RoundingMode::NegativeInfinity): ReplayableRoll
    {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw InvalidDefinition::because('A replay identifier is 32 lower-case hexadecimal digits.');
        }

        return new ReplayableRoll($id, $seed, $this->roller->roll($pool, $seed->randomizer(), $rounding));
    }
}
