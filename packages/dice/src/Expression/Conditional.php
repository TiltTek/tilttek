<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * A branch on the result of its subject: `1d20i[>=15]{"hit"}{"miss"}` gives the first branch when the condition holds,
 * the second otherwise — or the subject itself when there is no second. The condition judges the subject's value by
 * default, the dice that count in it with `all:` or `any:`, their sum with `total:`.
 */
final readonly class Conditional implements Node
{
    public function __construct(
        public Node $subject,
        public Condition $condition,
        public Node $then,
        public ?Node $else = null,
    ) {
        Branching::check($condition);
    }
}
