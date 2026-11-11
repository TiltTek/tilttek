<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * Keep the highest, keep the lowest, drop the highest or drop the lowest, written `kh`, `kl`, `dh` and `dl`.
 */
enum SelectionMode: string
{
    case KeepHighest = 'kh';
    case KeepLowest = 'kl';
    case DropHighest = 'dh';
    case DropLowest = 'dl';

    public function keeps(): bool
    {
        return self::KeepHighest === $this || self::KeepLowest === $this;
    }

    /**
     * Whether the dice this mode names, kept or dropped, are the highest.
     */
    public function highest(): bool
    {
        return self::KeepHighest === $this || self::DropHighest === $this;
    }
}
