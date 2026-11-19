<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Statistics;

/**
 * What the goodness-of-fit test lets one say of a die, from the least to the most unusual. None of them says that a die
 * is loaded: a test measures how rare the rolls would be for a fair die, never why they happened.
 */
enum Verdict: string
{
    /** Too few rolls for the test to be trusted: some face is expected fewer than five times. */
    case NotEnoughRolls = 'not_enough_rolls';

    /** A fair die gives rolls this uneven at least once in 100 series: chance explains them well. */
    case NothingUnusual = 'nothing_unusual';

    /** A fair die gives rolls this uneven between once in 1 000 and once in 100 series. */
    case SomewhatUnusual = 'somewhat_unusual';

    /** A fair die gives rolls this uneven fewer than once in 1 000 series. */
    case VeryUnusual = 'very_unusual';

    /** Below it, the rolls are somewhat unusual. */
    public const float SOMEWHAT_UNUSUAL_BELOW = 0.01;

    /** Below it, the rolls are very unusual. */
    public const float VERY_UNUSUAL_BELOW = 0.001;

    /** The verdict of a p-value computed on enough rolls. */
    public static function ofPValue(float $pValue): self
    {
        return match (true) {
            $pValue < self::VERY_UNUSUAL_BELOW => self::VeryUnusual,
            $pValue < self::SOMEWHAT_UNUSUAL_BELOW => self::SomewhatUnusual,
            default => self::NothingUnusual,
        };
    }
}
