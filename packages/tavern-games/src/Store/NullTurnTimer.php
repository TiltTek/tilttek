<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Store;

use Tilttek\TavernGames\Contract\TurnTimer;

/**
 * No clock: a turn lasts until it is played.
 */
final class NullTurnTimer implements TurnTimer
{
    public function started(string $tableId, string $seat, int $sequence): void
    {
    }

    public function stopped(string $tableId): void
    {
    }
}
