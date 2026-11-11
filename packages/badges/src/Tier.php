<?php

declare(strict_types=1);

namespace Tilttek\Badges;

use Tilttek\Badges\Exception\InvalidBadge;

/**
 * The place of a badge in a series of linked badges — bronze, silver, gold —, each with its own threshold: the series
 * is named by the application, and the rank starts at 1 for the first tier.
 */
final readonly class Tier
{
    public function __construct(
        public string $series,
        public int $rank,
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $series)) {
            throw InvalidBadge::because(\sprintf('"%s" is not a series name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.', $series));
        }

        if ($rank < 1) {
            throw InvalidBadge::because(\sprintf('The rank of a tier starts at 1; %d is not one.', $rank));
        }
    }
}
