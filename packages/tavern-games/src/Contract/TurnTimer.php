<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Contract;

/**
 * The clock of the turns, which the application may give: told when a seat's turn starts and when a table's turns
 * stop, it chooses the delay and, when it runs out, calls `TavernTables::timeout()` with the seat and the number of
 * moves it was told. The library depends on no clock: without one, a turn lasts until it is played.
 */
interface TurnTimer
{
    /**
     * A seat's turn starts after the given number of moves of the table.
     */
    public function started(string $tableId, string $seat, int $sequence): void;

    /**
     * The game is over: no turn is running at the table.
     */
    public function stopped(string $tableId): void;
}
