<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables;

/**
 * How the rows of a table are found.
 */
enum Lookup: string
{
    /** By the key of a row: `blinded`, `plate`, `level-5`. */
    case Key = 'key';

    /** By a value that one range of the table covers: a roll of 37 finds the row `31–45`; a level of 7 the row `5–8`. */
    case Range = 'range';
}
