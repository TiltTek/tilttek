<?php

declare(strict_types=1);

namespace Tilttek\Dice\Die;

use Override;
use Random\Randomizer;
use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * A Fudge die: faces −1, 0 and +1. `dF`, or `dF.2`, has two faces of each; `dF.1` has one −1, four blank faces and
 * one +1.
 */
final readonly class FudgeDie implements NumericDie
{
    public function __construct(
        public int $variant = 2,
    ) {
        if (1 !== $variant && 2 !== $variant) {
            throw InvalidDefinition::because(\sprintf('A Fudge die is dF.1 or dF.2; dF.%d is not.', $variant));
        }
    }

    #[Override]
    public function lowest(): int
    {
        return -1;
    }

    #[Override]
    public function highest(): int
    {
        return 1;
    }

    #[Override]
    public function roll(Randomizer $randomizer): int
    {
        if (2 === $this->variant) {
            return $randomizer->getInt(-1, 1);
        }

        return match ($randomizer->getInt(1, 6)) {
            1 => -1,
            6 => 1,
            default => 0,
        };
    }
}
