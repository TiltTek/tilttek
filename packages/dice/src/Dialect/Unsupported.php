<?php

declare(strict_types=1);

namespace Tilttek\Dice\Dialect;

/**
 * A construct a dialect cannot write — in the library's notation — and why.
 */
final readonly class Unsupported
{
    public function __construct(
        public string $construct,
        public string $reason,
    ) {
    }
}
