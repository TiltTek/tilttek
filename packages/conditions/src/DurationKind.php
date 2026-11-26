<?php

declare(strict_types=1);

namespace Tilttek\Conditions;

/**
 * How a condition's duration runs out: by rounds of play, by time in the fiction that the table advances, by real time
 * read from the clock, or never on its own.
 */
enum DurationKind: string
{
    case Rounds = 'rounds';
    case GameTime = 'game-time';
    case RealTime = 'real-time';
    case UntilRemoved = 'until-removed';
}
