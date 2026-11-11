<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability;

use Tilttek\DiceProbability\Computation\Weights;
use Tilttek\DiceProbability\Exact\Fraction;
use Tilttek\DiceProbability\Exact\Natural;

/**
 * Two expressions rolled against each other — an attack against a defence, `1d20+5` against `2d10+4` —: the
 * probability that the first is higher, that both are equal, and that the second is higher.
 */
final readonly class Versus
{
    public Fraction $first;

    public Fraction $tie;

    public Fraction $second;

    public function __construct(
        public Distribution $firstDistribution,
        public Distribution $secondDistribution,
    ) {
        $higher = Natural::zero();
        $equal = Natural::zero();
        $below = Natural::zero();
        $values = $secondDistribution->values();
        $next = 0;

        foreach ($firstDistribution->weights as $value => $weight) {
            // The weight of the second's values below this one, gathered as the values rise.
            while (isset($values[$next]) && $values[$next] < $value) {
                $below = $below->add($secondDistribution->weights[$values[$next]]);
                ++$next;
            }

            $higher = $higher->add($weight->multiply($below));
            $equal = $equal->add($weight->multiply($secondDistribution->weights[$value] ?? Natural::zero()));
        }

        $total = $firstDistribution->total->multiply($secondDistribution->total);
        $this->first = new Fraction($higher, $total);
        $this->tie = new Fraction($equal, $total);
        $this->second = new Fraction($total->subtract($higher)->subtract($equal), $total);
    }

    public function isEstimated(): bool
    {
        return $this->firstDistribution->isEstimated() || $this->secondDistribution->isEstimated();
    }

    /**
     * The margin of the probabilities at 95 % confidence, the margins of both estimates added; null when both are
     * exact.
     */
    public function margin(): ?float
    {
        return $this->isEstimated() ? ($this->firstDistribution->estimate?->margin() ?? 0.0) + ($this->secondDistribution->estimate?->margin() ?? 0.0) : null;
    }

    /**
     * The probability that an explosion cut at its depth took part in either expression.
     */
    public function remaining(): Fraction
    {
        return Weights::either($this->firstDistribution->remaining, $this->secondDistribution->remaining);
    }
}
