<?php

declare(strict_types=1);

namespace Tilttek\Dice\Die;

use Random\Randomizer;

/**
 * A die whose faces are every integer from its lowest to its highest: the dice that take modifiers.
 */
interface NumericDie extends DieType
{
    public function lowest(): int;

    public function highest(): int;

    /**
     * One face, drawn from the randomness given.
     */
    public function roll(Randomizer $randomizer): int;
}
