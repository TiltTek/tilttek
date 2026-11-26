<?php

declare(strict_types=1);

namespace Tilttek\Predictions;

use Tilttek\Predictions\Exception\InvalidPrediction;

/**
 * The Brier score of a forecast, in whole numbers: the sum, over the outcomes, of the square of the gap between the
 * probability given and what happened — 100 % for the outcome that happened, 0 % for the others —, in ten-thousandths.
 * 0 is a perfect forecast; 20000 is full confidence in an outcome that did not happen. The points are the share of
 * the maximum left once the score is taken from 20000, rounded half up: 50 % on each of two outcomes earns three
 * quarters of the points whatever happens, 70 % on the outcome that happened earns 91 %, and on the one that did not,
 * 51 %.
 */
final class Brier
{
    public const int WORST = 20_000;

    /**
     * @param array<int|string, int> $percents the probability of each outcome, in percent, summing to 100
     */
    public static function score(array $percents, string $outcome): int
    {
        if (!\array_key_exists($outcome, $percents)) {
            throw InvalidPrediction::because(\sprintf('The forecast gives no probability to "%s".', $outcome));
        }

        $score = 0;

        foreach ($percents as $key => $percent) {
            $gap = $percent - ((string) $key === $outcome ? 100 : 0);
            $score += $gap * $gap;
        }

        return $score;
    }

    /**
     * The points of a forecast with this score, out of the maximum.
     */
    public static function points(int $maximum, int $score): int
    {
        return intdiv($maximum * (self::WORST - $score) + self::WORST / 2, self::WORST);
    }
}
