<?php

declare(strict_types=1);

namespace Tilttek\Dice\Die;

use Override;
use Random\Randomizer;
use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * A die numbered from 1 to its number of sides: `d6`, `d20`, `d100`.
 */
final readonly class StandardDie implements NumericDie
{
    public function __construct(
        public int $sides,
    ) {
        if ($sides < 1) {
            throw InvalidDefinition::because(\sprintf('A die has at least one side; %d is not a number of sides.', $sides));
        }
    }

    #[Override]
    public function lowest(): int
    {
        return 1;
    }

    #[Override]
    public function highest(): int
    {
        return $this->sides;
    }

    #[Override]
    public function roll(Randomizer $randomizer): int
    {
        return $randomizer->getInt(1, $this->sides);
    }
}
