<?php

declare(strict_types=1);

namespace Tilttek\Heraldry;

/**
 * How two or three charges on the field are set when nothing else places them.
 */
enum Arrangement: string
{
    /** The usual: one above the other for two, two above one for three — unwritten for three. */
    case Usual = 'usual';

    /** In a row across the shield. */
    case InFess = 'in_fess';

    /** In a column down the shield. */
    case InPale = 'in_pale';
}
