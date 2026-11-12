<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals;

/**
 * How a plan spreads its target over its writing days.
 */
enum CurveShape: string
{
    /** The same share each writing day. */
    case Linear = 'linear';

    /** More at the start: the first writing day weighs about twice the last. */
    case FrontLoaded = 'front_loaded';

    /** More at the end: the last writing day weighs about twice the first. */
    case BackLoaded = 'back_loaded';

    /** Weights the author draws, stretched over the writing days. */
    case Custom = 'custom';
}
