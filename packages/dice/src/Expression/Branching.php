<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * The rule the conditions of `if` and `switch` share: they judge their subject as a whole.
 *
 * @internal
 */
final class Branching
{
    /**
     * @throws InvalidDefinition when the condition judges each die as it fell
     */
    public static function check(Condition $condition): void
    {
        if (Scope::Die === $condition->scope) {
            throw InvalidDefinition::because('A branch judges its subject as a whole: its value, all or any of its dice, or their total; the scope "die" judges each die.');
        }
    }
}
