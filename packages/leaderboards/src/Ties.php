<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

/**
 * How a board orders members with the same score: the first to reach it ranks first, each member on a rank of their
 * own; or they share the rank, and the next one skips as many — 1, 2, 2, 4.
 */
enum Ties: string
{
    case FirstReached = 'first';
    case Shared = 'shared';
}
