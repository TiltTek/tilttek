<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * Which values a modifier applies to: a comparison, a range, a remainder, or several joined by `&`, `|` and `^`.
 */
interface Validator
{
    public function matches(int $value): bool;

    /**
     * Whether every face from the lowest to the highest matches — what makes an explosion or a repeated reroll endless.
     * A validator that cannot tell without trying each face answers false beyond a thousand faces: the limits of a
     * roll stop what it could not foresee.
     */
    public function matchesAll(int $lowest, int $highest): bool;
}
