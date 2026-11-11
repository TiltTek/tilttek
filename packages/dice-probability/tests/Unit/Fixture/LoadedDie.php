<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Tests\Unit\Fixture;

use Override;
use Random\Randomizer;
use Tilttek\Dice\Die\NumericDie;

/**
 * A die of the application whose faces the library cannot list: it always shows 3.
 */
final readonly class LoadedDie implements NumericDie
{
    #[Override]
    public function lowest(): int
    {
        return 1;
    }

    #[Override]
    public function highest(): int
    {
        return 6;
    }

    #[Override]
    public function roll(Randomizer $randomizer): int
    {
        return 3;
    }
}
