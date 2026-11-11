<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * Reroll each die that matches, until it no longer does (`r1`, `r<3`) or once (`ro1`).
 */
final readonly class Reroll
{
    public function __construct(
        public Validator $on,
        public Repetition $repetition = Repetition::Repeated,
    ) {
    }
}
