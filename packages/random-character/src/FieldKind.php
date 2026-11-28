<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter;

/**
 * Where the value of a field comes from.
 */
enum FieldKind: string
{
    /** A draw of a generator of `tilttek/generator`: a name, a trait, a background. */
    case Generated = 'generator';

    /** A roll of `tilttek/dice`: an age, starting gold, hit points. */
    case Rolled = 'roll';
}
