<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability;

use Tilttek\DiceProbability\Exact\Fraction;

/**
 * The probability of an event: exact, or estimated with its margin.
 */
final readonly class Chance
{
    public Fraction $remaining;

    /**
     * @param ?Fraction $remaining the probability that an explosion cut at its depth took part; zero when none
     */
    public function __construct(
        public Fraction $probability,
        public ?Estimate $estimate = null,
        ?Fraction $remaining = null,
    ) {
        $this->remaining = $remaining ?? Fraction::zero();
    }

    public function isEstimated(): bool
    {
        return $this->estimate instanceof Estimate;
    }

    /**
     * The margin of the probability at 95 % confidence; null when it is exact.
     */
    public function margin(): ?float
    {
        return $this->estimate?->margin();
    }
}
