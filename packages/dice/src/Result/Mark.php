<?php

declare(strict_types=1);

namespace Tilttek\Dice\Result;

/**
 * The critical mark of a die, for display.
 */
enum Mark: string
{
    case CriticalSuccess = 'critical-success';
    case CriticalFailure = 'critical-failure';
}
