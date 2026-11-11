<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Exception;

use RuntimeException;
use Tilttek\Dice\Exception\CannotRoll;

/**
 * No distribution can be given: an outcome cannot be rolled, the faces carry no value, or the input is not one
 * expression, or not one set of dice.
 */
final class CannotCompute extends RuntimeException implements ProbabilityException
{
    public static function cannotRoll(CannotRoll $previous): self
    {
        return new self(\sprintf('An outcome of the expression cannot be rolled: %s', lcfirst($previous->getMessage())), previous: $previous);
    }

    public static function notNumeric(string $name): self
    {
        return new self(\sprintf('The faces of the die "%s" carry no value: they have no distribution.', $name));
    }

    public static function severalExpressions(int $count): self
    {
        return new self(\sprintf('A distribution is that of one expression; the pool holds %d.', $count));
    }

    public static function notASet(): self
    {
        return new self('An event is judged on the faces of one set of dice, such as "4d6".');
    }
}
