<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

/**
 * Where the sheet of a match stands.
 */
enum SheetStatus: string
{
    /** Being prepared: participants set, values entered ahead, not yet shown to the players. */
    case Prepared = 'prepared';

    /** Shown to the players, open to entries. */
    case Published = 'published';

    /** Final: its result is passed on; reopened when a score was entered too early. */
    case Closed = 'closed';
}
