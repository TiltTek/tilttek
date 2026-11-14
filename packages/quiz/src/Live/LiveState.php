<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Live;

/**
 * Where a live session stands.
 */
enum LiveState: string
{
    /** Players join; no question is shown yet. */
    case Lobby = 'lobby';

    /** A question is shown and takes answers until it is closed. */
    case Question = 'question';

    /** The question is closed: its answer and the standings are shown before the next one. */
    case Review = 'review';

    /** The session is over: its standings are final. */
    case Finished = 'finished';
}
