<?php

declare(strict_types=1);

namespace Tilttek\Dice\Die;

use Override;
use Random\Randomizer;
use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * A die whose faces are every integer from one bound to another: `d[-1..1]`, `d[0..9]`.
 */
final readonly class RangeDie implements NumericDie
{
    public function __construct(
        public int $from,
        public int $to,
    ) {
        if ($from > $to) {
            throw InvalidDefinition::because(\sprintf('A die goes from its lowest face to its highest; %d..%d does not.', $from, $to));
        }
    }

    #[Override]
    public function lowest(): int
    {
        return $this->from;
    }

    #[Override]
    public function highest(): int
    {
        return $this->to;
    }

    #[Override]
    public function roll(Randomizer $randomizer): int
    {
        return $randomizer->getInt($this->from, $this->to);
    }
}
