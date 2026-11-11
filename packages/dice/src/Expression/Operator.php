<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * The operations of integer arithmetic. A division, and a power with a negative exponent, round as the roll says —
 * down by default —; a remainder has the sign of the divisor.
 */
enum Operator: string
{
    case Add = '+';
    case Subtract = '-';
    case Multiply = '*';
    case Divide = '/';
    case Modulo = '%';
    case Power = '^';

    /**
     * How tightly the operator binds: a power before a negation, multiplication, division and remainder before addition
     * and subtraction.
     */
    public function precedence(): int
    {
        return match ($this) {
            self::Add, self::Subtract => 1,
            self::Multiply, self::Divide, self::Modulo => 2,
            self::Power => 4,
        };
    }
}
