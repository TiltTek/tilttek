<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability;

use InvalidArgumentException;

/**
 * How an estimated result was drawn: from how many rolls, from which seed, and why it is not exact.
 */
final readonly class Estimate
{
    /**
     * The 95 % confidence factor of the normal law.
     */
    private const float CONFIDENCE = 1.96;

    /**
     * @throws InvalidArgumentException when there is no sample
     */
    public function __construct(
        public int $samples,
        public int $seed,
        public EstimateReason $reason,
    ) {
        if ($samples < 1) {
            throw new InvalidArgumentException(\sprintf('An estimate is drawn from at least one sample; %d is not.', $samples));
        }
    }

    /**
     * The margin of every probability of the estimate, at 95 % confidence: the half-width of the interval in the worst
     * case, a probability of one half — 0.0069, or 0.69 points, for 20 000 samples.
     */
    public function margin(): float
    {
        return self::CONFIDENCE * sqrt(0.25 / $this->samples);
    }
}
