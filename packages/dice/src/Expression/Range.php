<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Override;
use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * The values from one bound to another, both included: `8..10`.
 */
final readonly class Range implements Validator
{
    public function __construct(
        public int $from,
        public int $to,
    ) {
        if ($from > $to) {
            throw InvalidDefinition::because(\sprintf('A range goes from its lower bound to its higher one; %d..%d does not.', $from, $to));
        }
    }

    #[Override]
    public function matches(int $value): bool
    {
        return $value >= $this->from && $value <= $this->to;
    }

    #[Override]
    public function matchesAll(int $lowest, int $highest): bool
    {
        return $this->from <= $lowest && $this->to >= $highest;
    }
}
