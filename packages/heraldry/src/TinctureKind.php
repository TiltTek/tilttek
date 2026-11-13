<?php

declare(strict_types=1);

namespace Tilttek\Heraldry;

/**
 * The three families of tinctures the rule of tincture tells apart.
 */
enum TinctureKind: string
{
    /** Or and argent: gold and silver, drawn yellow and white. */
    case Metal = 'metal';

    /** Gules, azure, vert, sable and purpure. */
    case Colour = 'colour';

    /** Ermine and vair, which go on metal and colour alike. */
    case Fur = 'fur';
}
