<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

/**
 * What one item weighs and the room it takes, by both measures, so that one item serves systems that count weight and
 * systems that count slots.
 *
 * The weight is that of one unit, 0 to 100,000, in the whole unit the system chooses: pounds, tenths of a pound for a
 * system that weighs coins, tenths of a Bulk for a system whose light items weigh a tenth. The slots are those of a
 * stack of `$perSlot` units, 0 to 1000: a two-handed sword takes 2 slots for each 1, twenty arrows take 1 slot together.
 * A stack started takes its full slots: 21 arrows take 2 slots.
 */
final readonly class Burden
{
    public const int MAX_WEIGHT = 100_000;

    public const int MAX_SLOTS = 1000;

    public const int MAX_PER_SLOT = 10_000;

    public function __construct(
        public int $weight = 0,
        public int $slots = 0,
        public int $perSlot = 1,
    ) {
        Text::between($weight, 0, self::MAX_WEIGHT, 'The weight of one unit');
        Text::between($slots, 0, self::MAX_SLOTS, 'The slots of a stack');
        Text::between($perSlot, 1, self::MAX_PER_SLOT, 'The units of a stack');
    }

    /**
     * Nothing to carry: an item that weighs nothing and takes no slot.
     */
    public static function none(): self
    {
        return new self();
    }

    /**
     * What `$quantity` units weigh, or the slots they take.
     */
    public function of(int $quantity, Measure $measure): int
    {
        return match ($measure) {
            Measure::Weight => $quantity * $this->weight,
            Measure::Slots => intdiv($quantity + $this->perSlot - 1, $this->perSlot) * $this->slots,
        };
    }
}
