<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Statistics;

/**
 * The upper tail of the chi-squared distribution: the probability that a fair die spreads its faces at least as
 * unevenly as the statistic says, its p-value.
 *
 * It is the regularized upper incomplete gamma function Q(k/2, x/2), computed by its power series below `a + 1` and by
 * its continued fraction above, and Γ(k/2) is exact for the whole and half-whole arguments the degrees of freedom give.
 *
 * @internal
 */
final class ChiSquared
{
    /** The relative precision at which a series or a continued fraction stops. */
    private const float PRECISION = 1.0E-15;

    /** The most terms either computation takes; both converge long before it for the degrees of a die. */
    private const int MAX_TERMS = 10_000;

    /** What stands for zero in the continued fraction, to keep its divisions defined. */
    private const float TINY = 1.0E-300;

    private function __construct()
    {
    }

    /**
     * @param float $statistic        the chi-squared statistic, at least zero
     * @param int   $degreesOfFreedom at least one
     */
    public static function upperTail(float $statistic, int $degreesOfFreedom): float
    {
        if ($statistic <= 0.0) {
            return 1.0;
        }

        $a = $degreesOfFreedom / 2;
        $x = $statistic / 2;
        $front = exp($a * log($x) - $x - self::logGamma($degreesOfFreedom));

        if ($x < $a + 1) {
            return max(0.0, 1.0 - $front * self::series($a, $x));
        }

        return min(1.0, $front * self::continuedFraction($a, $x));
    }

    /**
     * ln Γ(k/2), exact for a whole k: Γ(m) = (m − 1)! and Γ(m + ½) = √π × ½ × 3/2 × … × (m − ½).
     */
    public static function logGamma(int $degreesOfFreedom): float
    {
        if (0 === $degreesOfFreedom % 2) {
            $log = 0.0;

            for ($i = 2; $i < $degreesOfFreedom / 2; ++$i) {
                $log += log($i);
            }

            return $log;
        }

        $log = 0.5 * log(\M_PI);

        for ($i = 0; $i < ($degreesOfFreedom - 1) / 2; ++$i) {
            $log += log($i + 0.5);
        }

        return $log;
    }

    /**
     * Σ xⁿ / (a (a + 1) … (a + n)), the series of the lower incomplete gamma function divided by its front factor.
     */
    private static function series(float $a, float $x): float
    {
        $term = 1 / $a;
        $sum = $term;

        for ($n = 1; $n < self::MAX_TERMS && abs($term) > abs($sum) * self::PRECISION; ++$n) {
            $term *= $x / ($a + $n);
            $sum += $term;
        }

        return $sum;
    }

    /**
     * The continued fraction of the upper incomplete gamma function divided by its front factor, evaluated by the
     * modified method of Lentz.
     */
    private static function continuedFraction(float $a, float $x): float
    {
        $b = $x + 1 - $a;
        $c = 1 / self::TINY;
        $d = 1 / $b;
        $fraction = $d;

        for ($i = 1; $i < self::MAX_TERMS; ++$i) {
            $numerator = -$i * ($i - $a);
            $b += 2;
            $d = $numerator * $d + $b;
            $d = abs($d) < self::TINY ? self::TINY : $d;
            $c = $b + $numerator / $c;
            $c = abs($c) < self::TINY ? self::TINY : $c;
            $d = 1 / $d;
            $step = $d * $c;
            $fraction *= $step;

            if (abs($step - 1) < self::PRECISION) {
                break;
            }
        }

        return $fraction;
    }
}
