<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Application;

use Tilttek\Dice\Result\PoolResult;
use Tilttek\DiceBundle\Domain\Model\RecordedRoll;

/**
 * A recorded roll rolled again from its expression and its seed, and whether it gave what the journal holds.
 */
final readonly class Replay
{
    public function __construct(
        public RecordedRoll $recorded,
        public PoolResult $result,
    ) {
    }

    public function matches(): bool
    {
        return $this->result->toArray() === $this->recorded->result();
    }
}
