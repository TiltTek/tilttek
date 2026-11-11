<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * A node negated: `-1d4`.
 */
final readonly class Negation implements Node
{
    public function __construct(
        public Node $operand,
    ) {
    }
}
