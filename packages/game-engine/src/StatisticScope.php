<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

/**
 * Whom a statistic of the end of a game counts for.
 */
enum StatisticScope: string
{
    /** The whole table: the rounds played, the cards left in the deck. */
    case Table = 'table';

    /** Each seat: its points, the moves it made, the encounters it survived. */
    case Seat = 'seat';
}
