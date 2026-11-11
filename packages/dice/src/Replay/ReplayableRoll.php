<?php

declare(strict_types=1);

namespace Tilttek\Dice\Replay;

use Tilttek\Dice\Fairness\Seed;
use Tilttek\Dice\Result\PoolResult;

/**
 * A roll kept to be replayed: its identifier, its seed and its result, whose pool is its expression.
 */
final readonly class ReplayableRoll
{
    public function __construct(
        public string $id,
        public Seed $seed,
        public PoolResult $result,
    ) {
    }

    public function expression(): string
    {
        return $this->result->pool->canonical();
    }
}
