<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

/**
 * What became of a result entered.
 */
enum RecordingStatus: string
{
    /** The result is kept: the first entry of the table, or an organiser's decision. */
    case Recorded = 'recorded';
    /** The same result was kept already: nothing changes. */
    case Unchanged = 'unchanged';
    /** The result contradicts the one entered before: the table waits for an organiser to settle it. */
    case Conflict = 'conflict';
}
