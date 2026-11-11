<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Override;

/**
 * A comparator and its threshold: `>=7`, `=1`, `!=6`.
 */
final readonly class Comparison implements Validator
{
    public function __construct(
        public Comparator $comparator,
        public int $value,
    ) {
    }

    #[Override]
    public function matches(int $value): bool
    {
        return match ($this->comparator) {
            Comparator::Equal => $value === $this->value,
            Comparator::NotEqual => $value !== $this->value,
            Comparator::Greater => $value > $this->value,
            Comparator::GreaterOrEqual => $value >= $this->value,
            Comparator::Less => $value < $this->value,
            Comparator::LessOrEqual => $value <= $this->value,
        };
    }

    #[Override]
    public function matchesAll(int $lowest, int $highest): bool
    {
        return match ($this->comparator) {
            Comparator::Equal => $lowest === $this->value && $highest === $this->value,
            Comparator::NotEqual => $this->value < $lowest || $this->value > $highest,
            Comparator::Greater, Comparator::GreaterOrEqual => $this->matches($lowest),
            Comparator::Less, Comparator::LessOrEqual => $this->matches($highest),
        };
    }

    /**
     * Whether the comparison is written with its sign alone, outside brackets: every comparator but `!=`, which an
     * explosion would read as its own sign.
     */
    public function isPlain(): bool
    {
        return Comparator::NotEqual !== $this->comparator;
    }
}
