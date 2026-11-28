<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

/**
 * The three choices a member gives a number of players, beside the rating of 1 to 5.
 */
enum PlayerCountChoice: string
{
    case Best = 'best';
    case Recommended = 'recommended';
    case NotRecommended = 'not_recommended';
}
