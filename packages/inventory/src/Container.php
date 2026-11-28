<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

/**
 * What makes an item a container: the most it holds by weight and by slots — each 1 to 1,000,000,000, or none for no
 * bound by that measure — and whether what it holds weighs on its carrier: a magic bag whose contents weigh nothing
 * says `false`. A container always holds what its bounds allow, whatever measure the system counts.
 */
final readonly class Container
{
    public const int MAX_CAPACITY = 1_000_000_000;

    public function __construct(
        public ?int $maxWeight = null,
        public ?int $maxSlots = null,
        public bool $countsContents = true,
    ) {
        if (null !== $maxWeight) {
            Text::between($maxWeight, 1, self::MAX_CAPACITY, 'The weight a container holds');
        }

        if (null !== $maxSlots) {
            Text::between($maxSlots, 1, self::MAX_CAPACITY, 'The slots a container holds');
        }
    }

    /**
     * Whether contents of that weight and those slots fit.
     */
    public function holds(int $weight, int $slots): bool
    {
        return (null === $this->maxWeight || $weight <= $this->maxWeight)
            && (null === $this->maxSlots || $slots <= $this->maxSlots);
    }
}
