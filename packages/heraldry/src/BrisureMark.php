<?php

declare(strict_types=1);

namespace Tilttek\Heraldry;

/**
 * The marks of difference a coat of arms may carry.
 */
enum BrisureMark: string
{
    /** A narrow band across the chief with three points hanging from it: a lambel in French. */
    case Label = 'label';

    /** A narrow bend laid over everything: a bâton in French. */
    case Bendlet = 'bendlet';
}
