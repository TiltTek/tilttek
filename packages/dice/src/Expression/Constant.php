<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * A fixed value: the `3` of `2d6+3`.
 */
final readonly class Constant implements Node
{
    public function __construct(
        public int $value,
    ) {
    }
}
