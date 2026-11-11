<?php

declare(strict_types=1);

namespace Tilttek\Progression\Experience;

use Tilttek\Progression\Curve;
use Tilttek\Progression\Exception\InvalidProgression;

/**
 * The most experience a rule gives a subject in a period: two hundred a day, five hundred a week, fifty ever.
 */
final readonly class Cap
{
    public function __construct(
        public int $amount,
        public Period $period,
    ) {
        if ($amount < 1 || $amount > Curve::MAX_EXPERIENCE) {
            throw InvalidProgression::because(\sprintf('A cap is 1 to %d experience; this one is %d.', Curve::MAX_EXPERIENCE, $amount));
        }
    }
}
