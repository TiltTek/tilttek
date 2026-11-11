<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * How two validators are joined: both `&`, either `|`, exactly one `^`. `&` binds tighter than `|` and `^`.
 */
enum Junction: string
{
    case And = '&';
    case Or = '|';
    case Xor = '^';

    public function precedence(): int
    {
        return self::And === $this ? 2 : 1;
    }
}
