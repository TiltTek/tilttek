<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Exception;

use Throwable;

/**
 * Every exception the library throws; the exceptions of `tilttek/game-engine` pass through unchanged.
 */
interface MiniGameException extends Throwable
{
}
