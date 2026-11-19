<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Application;

/**
 * What the hint screen shows.
 */
enum ScreenKind: string
{
    /** A hint pushed by the host: its text is read from the scenario by its puzzle and tier. */
    case Hint = 'hint';

    /** A message the host typed. */
    case Message = 'message';

    /** Nothing: the host cleared the screen. */
    case Cleared = 'cleared';
}
