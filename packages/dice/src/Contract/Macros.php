<?php

declare(strict_types=1);

namespace Tilttek\Dice\Contract;

use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Pool;

/**
 * The macros an input may name: `$attack + 2` takes the tree of the macro `attack` as an operand, read as one token —
 * a `$` and the letters and digits written against it —, never by replacing text. The parser lower-cases the name.
 */
interface Macros
{
    /**
     * A name: a letter, then letters and digits, 32 characters at most, in lower case.
     */
    public const string NAME_PATTERN = '/^[a-z][a-z0-9]{0,31}$/D';

    /**
     * The pool the macro of this name stands for, its own macros resolved; null when no macro has this name.
     *
     * @throws InvalidExpression when the macro does not parse, or names itself through other macros
     * @throws LimitExceeded     when it goes past a limit
     */
    public function pool(string $name): ?Pool;
}
