<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * Two nodes joined by an operator: `(1d8+2)*2`.
 */
final readonly class Operation implements Node
{
    public function __construct(
        public Operator $operator,
        public Node $left,
        public Node $right,
    ) {
    }
}
