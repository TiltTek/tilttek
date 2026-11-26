<?php

declare(strict_types=1);

namespace Tilttek\MiniGames;

/**
 * One square of a shared result: how a guess or a call went, never what it was.
 */
enum Cell: string
{
    /** Right, and in the right place. */
    case Hit = 'hit';

    /** Right, but in another place. */
    case Near = 'near';

    /** Not right. */
    case Miss = 'miss';

    /** Not played. */
    case Empty = 'empty';
}
