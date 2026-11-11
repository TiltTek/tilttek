<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * A case of a switch: the condition its subject is judged by, and the branch it gives when the condition holds.
 */
final readonly class SwitchCase
{
    public function __construct(
        public Condition $when,
        public Node $then,
    ) {
        Branching::check($when);
    }
}
