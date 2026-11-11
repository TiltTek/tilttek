<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Draw;

/**
 * Where the faces of a result come from.
 */
enum DrawSource: string
{
    /** Rolled by `LootDrawer` from a seed committed before and revealed after: anyone can check it. */
    case Verifiable = 'verifiable';

    /** Physical dice, rolled in front of a cashier and entered as they fell. */
    case Physical = 'physical';
}
