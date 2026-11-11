<?php

declare(strict_types=1);

namespace Tilttek\Dice\Result;

/**
 * What a roll could not do exactly, and says: groups that reach a sum found greedily, beyond the dice searched for the
 * most groups.
 */
enum Notice: string
{
    case GreedyGroups = 'greedy-groups';
}
