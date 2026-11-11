<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * The sets of dice showing the same value, at least `width` of them, whose value matches the height when one is
 * given: `10d10o2[>=7]` counts the pairs, triples and more of 7 or above.
 */
final readonly class Occurrences
{
    public function __construct(
        public int $width = 2,
        public ?Validator $height = null,
    ) {
        if ($width < 1) {
            throw InvalidDefinition::because(\sprintf('A set of occurrences holds at least one die; %d is not.', $width));
        }
    }
}
