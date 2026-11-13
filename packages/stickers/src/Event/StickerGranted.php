<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Event;

use Phil\Resource\ResourceReference;

/**
 * Told when a holder is given a sticker they did not own: the application may record the sheets the sticker belongs to.
 */
final readonly class StickerGranted
{
    public function __construct(
        public ResourceReference $holder,
        public string $sticker,
    ) {
    }
}
