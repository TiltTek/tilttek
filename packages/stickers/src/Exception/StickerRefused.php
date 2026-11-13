<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Exception;

use RuntimeException;

/**
 * A placement the rules refuse: a sticker the holder does not own, retired or unknown, a surface that is not theirs, a
 * limit of the site reached, or a placement that is not theirs to move or remove.
 */
final class StickerRefused extends RuntimeException implements StickersException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
