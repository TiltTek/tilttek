<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

/**
 * Which value wins, for a majority or a tie-breaker.
 */
enum Direction: string
{
    case Most = 'most';
    case Fewest = 'fewest';
}
