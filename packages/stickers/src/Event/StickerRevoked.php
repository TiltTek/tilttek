<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Event;

use Phil\Resource\ResourceReference;

/**
 * Told when a sticker is taken back from a holder, with the number of their placements of it removed.
 */
final readonly class StickerRevoked
{
    public function __construct(
        public ResourceReference $holder,
        public string $sticker,
        public int $placementsRemoved,
    ) {
    }
}
