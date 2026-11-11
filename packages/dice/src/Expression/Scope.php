<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * What a condition is checked against: each die's final value — the default —, each die as it fell, before its floor,
 * ceiling or transformation, or the dice that count taken together: all of them, at least one, or their total.
 */
enum Scope: string
{
    case Value = 'value';
    case Die = 'die';
    case All = 'all';
    case Any = 'any';
    case Total = 'total';

    /**
     * Whether the condition judges each die on its own.
     */
    public function eachDie(): bool
    {
        return self::Value === $this || self::Die === $this;
    }
}
