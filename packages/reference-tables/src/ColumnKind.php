<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables;

/**
 * What the cells of a column hold.
 */
enum ColumnKind: string
{
    /** A text in one or more languages: a result, an effect, a name. */
    case Text = 'text';

    /** An integer: a bonus, a cost, a difficulty, a number of experience points. */
    case Number = 'number';

    /** A dice expression in the notation of `tilttek/dice`, rolled when the table says so: `2d4` appearing, `1d6+1`. */
    case Dice = 'dice';
}
