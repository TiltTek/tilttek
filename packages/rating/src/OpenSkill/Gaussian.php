<?php

declare(strict_types=1);

namespace Tilttek\Rating\OpenSkill;

/**
 * The standard normal distribution and the corrections of a win, a loss and a draw that the Thurstone-Mosteller model
 * applies, after Weng and Lin (2011) and Herbrich, Minka and Graepel (2007).
 *
 * The complementary error function is computed by its Maclaurin series below 2 and by its continued fraction above,
 * both to the precision of a float.
 *
 * @internal
 */
final class Gaussian
{
    /** Below this difference, the probability of a win is too small for a float, and its correction is its limit. */
    private const float FAR = -35.0;

    private function __construct()
    {
    }

    /**
     * The density of the standard normal distribution.
     */
    public static function pdf(float $x): float
    {
        return exp(-$x * $x / 2) / sqrt(2 * \M_PI);
    }

    /**
     * The cumulative distribution of the standard normal distribution.
     */
    public static function cdf(float $x): float
    {
        return self::erfc(-$x / \M_SQRT2) / 2;
    }

    /**
     * The complementary error function, `1 − erf(x)`.
     */
    public static function erfc(float $x): float
    {
        if ($x < 0) {
            return 2 - self::erfc(-$x);
        }

        if ($x < 2) {
            // erf(x) = 2/√π · Σ (−1)ⁿ x²ⁿ⁺¹ / (n! (2n + 1)), summed until a term no longer counts.
            $term = $x;
            $sum = $x;

            for ($n = 1; $n <= 60 && abs($term) > 1e-17; ++$n) {
                $term *= -$x * $x / $n;
                $sum += $term / (2 * $n + 1);
            }

            return 1 - 2 / sqrt(\M_PI) * $sum;
        }

        // erfc(x) = e^(−x²) / √π · 1 / (x + (1/2) / (x + 1 / (x + (3/2) / (x + 2 / (x + …))))), from its tail.
        $fraction = $x;

        for ($n = 60; $n >= 1; --$n) {
            $fraction = $x + $n / 2 / $fraction;
        }

        return exp(-$x * $x) / (sqrt(\M_PI) * $fraction);
    }

    /**
     * The corrections of the mean and of the variance of the side that won, by the difference of the means `x` and the
     * draw margin `t`, both divided by the deviation of the difference.
     *
     * @return array{0: float, 1: float}
     */
    public static function win(float $x, float $t): array
    {
        $d = $x - $t;

        if ($d < self::FAR) {
            return [-$d, 1.0];
        }

        $v = self::pdf($d) / self::cdf($d);

        return [$v, $v * ($v + $d)];
    }

    /**
     * The corrections of the mean and of the variance of a side that drew.
     *
     * @return array{0: float, 1: float}
     */
    public static function draw(float $x, float $t): array
    {
        $mass = self::cdf($t - $x) - self::cdf(-$t - $x);

        if ($mass < 1e-300) {
            return [$x < 0 ? -$x - $t : -$x + $t, 1.0];
        }

        $v = (self::pdf(-$t - $x) - self::pdf($t - $x)) / $mass;

        return [$v, $v * $v + (($t - $x) * self::pdf($t - $x) + ($t + $x) * self::pdf($t + $x)) / $mass];
    }
}
