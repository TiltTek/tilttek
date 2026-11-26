<?php

declare(strict_types=1);

namespace Tilttek\Handouts;

/**
 * What happened to a handout, to whom it is revealed, or to what a player has seen of it.
 */
enum Change: string
{
    case Created = 'created';

    case Edited = 'edited';

    case Removed = 'removed';

    case Revealed = 'revealed';

    case Concealed = 'concealed';

    case Staged = 'staged';

    case Unstaged = 'unstaged';

    case Seen = 'seen';

    case Forgotten = 'forgotten';
}
