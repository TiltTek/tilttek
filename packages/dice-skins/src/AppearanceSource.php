<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins;

/**
 * Where the appearance of a die comes from, in the order the library looks for it.
 */
enum AppearanceSource: string
{
    /**
     * A die of a set the member owns, chosen for its number of sides.
     */
    case Owned = 'owned';

    /**
     * The skin the member chose for this number of sides.
     */
    case ChosenForSides = 'chosen-for-sides';

    /**
     * The skin the member chose for all their dice.
     */
    case ChosenForAll = 'chosen-for-all';

    /**
     * The site's default skin.
     */
    case Default = 'default';
}
