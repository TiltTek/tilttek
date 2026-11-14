<?php

declare(strict_types=1);

namespace Tilttek\Quiz\SelfPaced;

/**
 * When a member at their own pace sees how right their answers were, and so the right answers.
 */
enum Feedback: string
{
    /** Once the play is over: nothing of a play that is not over tells a right answer. */
    case AfterPlay = 'after_play';

    /** After each answer, for a quiz meant to teach as it goes. */
    case AfterEachAnswer = 'after_each_answer';
}
