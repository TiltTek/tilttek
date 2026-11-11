<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * What a placeholder of a sentence writes: `%1` the result it speaks of — the subject of its branch, or the
 * instruction before —, `%2` the values of that result's dice, `%3` the results of every instruction before, `$n` the
 * result of an instruction, `@n` the values of its dice.
 */
enum PlaceholderKind: string
{
    case Subject = '%1';
    case SubjectDice = '%2';
    case Before = '%3';
    case Result = '$';
    case Dice = '@';

    /**
     * Whether the placeholder names its instruction.
     */
    public function namesInstruction(): bool
    {
        return self::Result === $this || self::Dice === $this;
    }
}
