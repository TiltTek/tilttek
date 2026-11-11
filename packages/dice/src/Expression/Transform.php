<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * A die that matches takes another value: `t[>5:15]` turns every face above 5 into 15.
 */
final readonly class Transform
{
    public function __construct(
        public Condition $when,
        public int $value,
    ) {
        if (!$when->scope->eachDie()) {
            throw InvalidDefinition::because(\sprintf('A transformation judges each die; the scope "%s" judges them together.', $when->scope->value));
        }
    }
}
