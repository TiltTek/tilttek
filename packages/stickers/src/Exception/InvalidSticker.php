<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a sticker without its text alternative, an image reference, a licence, a position, a
 * scale, a rotation or limits out of bounds, or a placement request that does not read.
 */
final class InvalidSticker extends InvalidArgumentException implements StickersException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
