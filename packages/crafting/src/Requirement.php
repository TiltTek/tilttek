<?php

declare(strict_types=1);

namespace Tilttek\Crafting;

/**
 * One ingredient of a planned craft: how many it needs, the items of the holder set aside for it, and what is still
 * missing.
 */
final readonly class Requirement
{
    /**
     * @param array<string, int> $allocation the quantity of each item set aside, in the order of their codes
     */
    public function __construct(
        public Ingredient $ingredient,
        public int $needed,
        public array $allocation,
    ) {
    }

    public function held(): int
    {
        return array_sum($this->allocation);
    }

    public function missing(): int
    {
        return max(0, $this->needed - $this->held());
    }

    public function isMet(): bool
    {
        return 0 === $this->missing();
    }
}
