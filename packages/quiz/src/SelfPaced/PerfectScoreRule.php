<?php

declare(strict_types=1);

namespace Tilttek\Quiz\SelfPaced;

/**
 * Which perfect plays at one's own pace dispatch `PerfectScore`.
 */
enum PerfectScoreRule: string
{
    /** The perfect play that counts, the member's first of the quiz: once per quiz and member at most. */
    case OncePerQuizAndMember = 'once_per_quiz_and_member';

    /** Every perfect play, practice ones included, the event saying which are practice. */
    case EveryPerfectPlay = 'every_perfect_play';
}
