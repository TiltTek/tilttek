<?php

declare(strict_types=1);

namespace Tilttek\Celebrations;

/**
 * How much a celebration moves: fully, reduced to a fade, or not at all.
 */
enum Motion: string
{
    case Full = 'full';

    case Reduced = 'reduced';

    case None = 'none';
}
