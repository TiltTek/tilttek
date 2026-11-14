<?php

declare(strict_types=1);

namespace Tilttek\Quiz\SelfPaced;

/**
 * What a member's second play of a quiz at their own pace is: its questions were seen before, so it never counts.
 */
enum Replays: string
{
    /** Played for practice: scored for the member to see, marked practice, perfect for the events only under `PerfectScoreRule::EveryPerfectPlay`. */
    case Practice = 'practice';

    /** Refused: a member plays each quiz once. */
    case Refused = 'refused';
}
