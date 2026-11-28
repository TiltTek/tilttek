<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Contract;

use Tilttek\GameNight\PlayedGame;

/**
 * Receives the games played at a night, for the application's play log.
 */
interface PlayJournal
{
    public function record(PlayedGame $play): void;
}
