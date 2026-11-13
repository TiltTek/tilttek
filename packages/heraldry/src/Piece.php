<?php

declare(strict_types=1);

namespace Tilttek\Heraldry;

/**
 * An ordinary in its tincture.
 */
final readonly class Piece
{
    public function __construct(
        public Ordinary $ordinary,
        public Tincture $tincture,
    ) {
    }
}
