<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Exception;

use Throwable;

/**
 * Every exception the library throws; the exceptions of `tilttek/game-engine` pass through unchanged.
 */
interface TavernGamesException extends Throwable
{
}
