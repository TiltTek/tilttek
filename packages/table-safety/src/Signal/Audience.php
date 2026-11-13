<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Signal;

/**
 * Who sees a signal: the whole table, or the game master alone — the discreet signal.
 */
enum Audience: string
{
    case Table = 'table';
    case GameMaster = 'game-master';
}
