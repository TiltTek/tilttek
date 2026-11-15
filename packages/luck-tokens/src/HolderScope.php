<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens;

/**
 * Who holds a kind of token, and so what its cap counts: the tokens of each character, or of each player whatever
 * character they play. The application gives the holder's reference accordingly.
 */
enum HolderScope: string
{
    case Character = 'character';
    case Player = 'player';
}
