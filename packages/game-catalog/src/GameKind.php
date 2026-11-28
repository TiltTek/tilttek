<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

/**
 * How a game is played, which the collection import filters on: role-playing games first.
 */
enum GameKind: string
{
    case Board = 'board';
    case Card = 'card';
    case RolePlaying = 'role-playing';
    case Miniatures = 'miniatures';
    case Larp = 'larp';
}
