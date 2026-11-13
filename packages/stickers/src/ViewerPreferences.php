<?php

declare(strict_types=1);

namespace Tilttek\Stickers;

/**
 * What a viewer chose: to see the stickers placed on the surfaces they read, or not. A viewer who turns them off sees
 * the surfaces without any sticker.
 */
final readonly class ViewerPreferences
{
    public function __construct(
        public bool $showStickers = true,
    ) {
    }

    public static function stickersOff(): self
    {
        return new self(false);
    }
}
