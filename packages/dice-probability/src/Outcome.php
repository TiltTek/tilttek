<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability;

use Tilttek\DiceProbability\Exact\Fraction;

/**
 * One value of a distribution, with the probability of rolling exactly it, at least it and at most it.
 */
final readonly class Outcome
{
    public function __construct(
        public int $value,
        public Fraction $probability,
        public Fraction $atLeast,
        public Fraction $atMost,
    ) {
    }
}
