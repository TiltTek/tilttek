<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar;

/**
 * Where a box stands for one member at one moment.
 */
enum BoxState: string
{
    /** Its day has not come: closed, its surprise hidden. */
    case Locked = 'locked';

    /** Its day has come and its window is open: the member may open it. */
    case Openable = 'openable';

    /** The member opened it: its surprise is shown. */
    case Opened = 'opened';

    /** Its window ended before the member opened it. */
    case Missed = 'missed';
}
