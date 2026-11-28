<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

/**
 * What a game system counts to tell how much a carrier carries: the weight of the items, in the unit the system chooses,
 * or the slots they take.
 */
enum Measure: string
{
    case Weight = 'weight';

    case Slots = 'slots';
}
