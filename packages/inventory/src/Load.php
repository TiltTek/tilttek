<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

use Phil\Resource\ResourceReference;

/**
 * What a carrier carries in an inventory, by the measure its system counts: the total, its capacity, the band of load
 * it is in, and the most the last band allows. Over its capacity, the band is null.
 */
final readonly class Load
{
    public function __construct(
        public ResourceReference $carrier,
        public Measure $measure,
        public int $carried,
        public int $capacity,
        public ?LoadBand $band,
        public int $limit,
    ) {
    }

    public function isOver(): bool
    {
        return !$this->band instanceof LoadBand;
    }

    /**
     * What it can still take before the last band is passed, 0 when it is over.
     */
    public function room(): int
    {
        return max(0, $this->limit - $this->carried);
    }
}
