<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Computation;

/**
 * The work an exact computation may do — each weight multiplied or added counts one —, past which it gives way to an
 * estimate.
 *
 * @internal
 */
final class Budget
{
    private int $spent = 0;

    public function __construct(
        public readonly int $limit,
    ) {
    }

    /**
     * @throws TooLarge when the work goes past the limit
     */
    public function spend(int $work): void
    {
        $this->spent += $work;

        if ($this->spent > $this->limit) {
            throw new TooLarge();
        }
    }
}
