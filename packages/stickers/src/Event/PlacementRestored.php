<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Event;

/**
 * Told when moderation shows a hidden placement again.
 */
final readonly class PlacementRestored
{
    public function __construct(
        public string $placement,
    ) {
    }
}
