<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use Tilttek\Collections\Exception\InvalidCollection;

/**
 * How rare an item is: a code — `common`, `rare`, `legendary` — and a weight, the share of the draws that land on it
 * when nothing says otherwise. A catalog declares its rarities from the most common to the rarest.
 */
final readonly class Rarity
{
    public const int MAX_WEIGHT = 1_000_000;

    public function __construct(
        public string $code,
        public int $weight,
    ) {
        Names::check($code, 'rarity');

        if ($weight < 1 || $weight > self::MAX_WEIGHT) {
            throw InvalidCollection::because(\sprintf('The weight of a rarity is 1 to %d; "%s" weighs %d.', self::MAX_WEIGHT, $code, $weight));
        }
    }
}
