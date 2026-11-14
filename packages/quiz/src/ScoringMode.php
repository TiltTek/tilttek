<?php

declare(strict_types=1);

namespace Tilttek\Quiz;

/**
 * What a quiz rewards: being right, the default, or being right and quick.
 */
enum ScoringMode: string
{
    /** Points for being right only: the time a player takes never costs them. */
    case Accuracy = 'accuracy';

    /** Points for being right, and a bonus for a right answer given before the time is up, larger the sooner. */
    case Speed = 'speed';
}
