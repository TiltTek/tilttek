<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

/**
 * What a container holds against its bounds: the weight and the slots of what is directly inside it — a container
 * inside counted with what it holds, unless its contents weigh nothing —, and its bounds, null for none.
 */
final readonly class Fill
{
    public function __construct(
        public string $container,
        public int $weight,
        public int $slots,
        public ?int $maxWeight,
        public ?int $maxSlots,
    ) {
    }

    /**
     * The weight it can still take, or null without a bound by weight.
     */
    public function weightLeft(): ?int
    {
        return null === $this->maxWeight ? null : max(0, $this->maxWeight - $this->weight);
    }

    /**
     * The slots it can still take, or null without a bound by slots.
     */
    public function slotsLeft(): ?int
    {
        return null === $this->maxSlots ? null : max(0, $this->maxSlots - $this->slots);
    }
}
