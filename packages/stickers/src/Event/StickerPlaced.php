<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Event;

use Phil\Resource\ResourceReference;

/**
 * Told when a holder places a sticker, shown or hidden by moderation: identifiers only.
 */
final readonly class StickerPlaced
{
    public function __construct(
        public string $placement,
        public ResourceReference $holder,
        public string $sticker,
        public ResourceReference $surface,
        public bool $hidden,
    ) {
    }
}
