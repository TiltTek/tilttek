<?php

declare(strict_types=1);

namespace Tilttek\Points\Rule;

use Tilttek\Points\Exception\InvalidPoints;
use Tilttek\Points\Movement;

/**
 * The most points a rule gives a member in a period: fifty a week, a hundred a day, ten ever.
 */
final readonly class Cap
{
    public function __construct(
        public int $amount,
        public Period $period,
    ) {
        if ($amount < 1 || $amount > Movement::MAX_AMOUNT) {
            throw InvalidPoints::because(\sprintf('A cap is 1 to %d points; this one is %d.', Movement::MAX_AMOUNT, $amount));
        }
    }
}
