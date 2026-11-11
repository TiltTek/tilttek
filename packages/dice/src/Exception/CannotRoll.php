<?php

declare(strict_types=1);

namespace Tilttek\Dice\Exception;

use RuntimeException;

/**
 * An expression that parses but cannot be rolled: a die nobody declared, faces without values in arithmetic, dice whose
 * rolled count or sides cannot be rolled, "@" with no set before it, a text or a list where a number is computed, an
 * item that is not there, a list or a repetition whose rolled count cannot be drawn, a division by zero, or a result
 * too large for an integer.
 */
final class CannotRoll extends RuntimeException implements DiceException
{
    public static function unknownDie(string $name): self
    {
        return new self(\sprintf('No die named "%s" is declared.', $name));
    }

    public static function notNumeric(string $name): self
    {
        return new self(\sprintf('The faces of the die "%s" carry no value: it cannot take part in arithmetic.', $name));
    }

    public static function invalidDice(string $reason): self
    {
        return new self(\sprintf('The dice cannot be rolled once their count and sides are known: %s', lcfirst($reason)));
    }

    public static function nothingBefore(): self
    {
        return new self('"@" judges the dice of the set before it, and no set comes before it in the expression.');
    }

    public static function notANumber(string $what): self
    {
        return new self(\sprintf('The expression computes with %s, which is not a number.', $what));
    }

    public static function noInstruction(int $instruction): self
    {
        return new self(\sprintf('Instruction %d does not come before the one that speaks of it.', $instruction));
    }

    public static function noItem(int $instruction, int $index, int $count): self
    {
        return new self(\sprintf('Instruction %d has %d items: item %d is not one of them.', $instruction, $count, $index));
    }

    public static function invalidCount(string $reason): self
    {
        return new self(\sprintf('The count rolled cannot be used: %s', lcfirst($reason)));
    }

    public static function divisionByZero(): self
    {
        return new self('The expression divides by zero.');
    }

    public static function overflow(): self
    {
        return new self('The result is too large for an integer.');
    }
}
