<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Event;

/**
 * Told when a holder removes one of their placements.
 */
final readonly class PlacementRemoved
{
    public function __construct(
        public string $placement,
    ) {
    }
}
