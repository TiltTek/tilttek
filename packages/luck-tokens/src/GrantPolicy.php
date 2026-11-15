<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens;

/**
 * Who may grant a kind of token.
 */
enum GrantPolicy: string
{
    /**
     * The game masters of the table only, the default.
     */
    case GameMasters = 'game-masters';

    /**
     * Anyone at the table: a player who rewards another's good idea, for instance.
     */
    case Anyone = 'anyone';
}
