<?php

declare(strict_types=1);

namespace Tilttek\Conditions;

/**
 * The start or the end of a turn or of a round: the moments a duration in rounds counts down and a value decays.
 */
enum Boundary: string
{
    case Start = 'start';
    case End = 'end';
}
