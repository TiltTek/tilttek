<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler;

/**
 * A member's answer for one session. A member who has not answered has no reply, and counts as coming for the quorum.
 */
enum Reply: string
{
    case Coming = 'coming';

    /** Announced at least the series' notice before the session. */
    case Absent = 'absent';

    /** Announced later than the notice: recorded all the same, and the game master decides. */
    case AbsentLate = 'absent-late';

    public function isAbsent(): bool
    {
        return self::Coming !== $this;
    }
}
