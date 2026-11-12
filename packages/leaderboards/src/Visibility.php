<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

/**
 * How a member appears to the others on a board: not at all — their rank is shown without a name, and they alone see
 * it is theirs —, under the name the application gives them, or under a pseudonym they chose. Hidden is where every
 * member starts: nobody is ranked publicly without choosing it.
 */
enum Visibility: string
{
    case Hidden = 'hidden';
    case Named = 'named';
    case Pseudonym = 'pseudonym';
}
