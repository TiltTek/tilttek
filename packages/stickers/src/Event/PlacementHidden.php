<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Event;

/**
 * Told when moderation hides a placement, with the reason it names.
 */
final readonly class PlacementHidden
{
    public function __construct(
        public string $placement,
        public string $reason,
    ) {
    }
}
