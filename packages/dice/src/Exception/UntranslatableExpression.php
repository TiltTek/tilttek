<?php

declare(strict_types=1);

namespace Tilttek\Dice\Exception;

use RuntimeException;

/**
 * The text of a translation asked for although the dialect cannot write every construct of the expression.
 */
final class UntranslatableExpression extends RuntimeException implements DiceException
{
    /**
     * @param list<string> $constructs
     */
    public static function in(string $dialect, array $constructs): self
    {
        return new self(\sprintf('%s cannot write %s.', $dialect, implode(', ', $constructs)));
    }
}
